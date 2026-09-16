/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// node test/run_regulatory_selector_browser.cjs <Dolibarr htdocs>
// Requires Playwright with Chromium, or PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH.
// No ERP access: real card bootstrap, native Form/Select2, synthetic records.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const core = path.resolve(process.argv[2]);
const fixtures = JSON.parse(execFileSync('php', [path.join(__dirname, 'run_regulatory_selector.php'), core, '--fixture'], { encoding: 'utf8' }));
const jquery = fs.readFileSync(path.join(core, 'includes/jquery/js/jquery.min.js'), 'utf8');
const select2 = fs.readFileSync(path.join(core, 'includes/jquery/plugins/select2/dist/js/select2.full.js'), 'utf8');
const css = fs.readFileSync(path.join(core, 'includes/jquery/plugins/select2/dist/css/select2.css'), 'utf8');

(async () => {
	const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined });
	try {
		const page = await browser.newPage();
		page.setDefaultTimeout(10000);
		const errors = [];
		page.on('pageerror', error => errors.push(error.message));
		let checks = 0;
		async function load(name) {
			await page.goto('about:blank');
			await page.setContent('<style>' + css + '</style><script>' + jquery + '</script><script>' + select2 + '</script>' + fixtures[name]);
			await page.waitForFunction(() => jQuery('#fk_requirement').data('select2') && document.querySelector('#fk_requirement').options.length <= 3);
		}
		async function checkOptions(ids, selected) {
			assert.deepEqual(await page.locator('#fk_requirement option').evaluateAll(options => options.map(option => option.value).filter(value => Number(value) > 0)), ids);
			assert.equal(Number(await page.locator('#fk_requirement').inputValue() || 0) > 0 ? await page.locator('#fk_requirement').inputValue() : '', selected);
			// Opening Select2 checks its rendered results, not just the original select.
			await page.locator('#fk_requirement + .select2-container .select2-selection').click();
			const texts = await page.locator('.select2-results__option').allTextContents();
			const vehicle = await page.locator('#fk_vehicle').inputValue();
			assert.ok(texts.every(text => !text.includes(vehicle === '1' ? 'FY-765-CT' : 'EN-026-EX')));
			if (vehicle !== '2') assert.ok(texts.every(text => !text.includes('FY-765-CT')));
			assert.equal(texts.filter(text => text.includes(' — ')).length, ids.length);
			await page.keyboard.press('Escape');
			checks++;
		}
		async function chooseVehicle(label) {
			await page.locator('#select2-fk_vehicle-container').click();
			await page.getByRole('option', { name: label, exact: true }).click();
		}
		await load('vehicle');
		await checkOptions(['11', '12'], '');
		await page.selectOption('#fk_requirement', '11');
		await chooseVehicle('FY-765-CT');
		await checkOptions(['21', '22'], '');
		await chooseVehicle('No requirement');
		await checkOptions([], '');
		await chooseVehicle('EN-026-EX');
		await checkOptions(['11', '12'], '');
		await page.selectOption('#fk_vehicle', '-1');
		await checkOptions([], '');
		for (const [name, ids, selected] of [
			['blank', [], ''], ['schedule', ['21', '22'], '21'],
			['conflicting_link', ['11', '12'], ''], ['failed_post', ['11', '12'], ''],
			['failed_post_empty_vehicle', [], ''], ['edit', ['11', '12'], '12'], ['no_requirements', [], ''],
		]) {
			await load(name);
			await checkOptions(ids, selected);
		}
		const plain = await browser.newPage({ javaScriptEnabled: false });
		await plain.setContent(fixtures.vehicle);
		assert.deepEqual(await plain.locator('#fk_requirement option').allTextContents(), ['\u00a0', 'EN-026-EX — Contrôle technique', 'EN-026-EX — Pollution']);
		assert.deepEqual(errors, []);
		console.log(`${checks} Select2 scenarios and initial rendering without JavaScript passed`);
	} finally {
		await browser.close();
	}
})().catch(error => { console.error(error); process.exitCode = 1; });
