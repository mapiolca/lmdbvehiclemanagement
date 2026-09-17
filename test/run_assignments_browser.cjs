/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// node test/run_assignments_browser.cjs <Dolibarr htdocs> <Multicompany root>
// Local synthetic HTML only. Requires Playwright and Chromium or its executable path.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const core = path.resolve(process.argv[2]);
const fixtures = JSON.parse(execFileSync('php', ['-d', 'extension=pdo_sqlite', path.join(__dirname, 'run_assignments.php'), core, path.resolve(process.argv[3]), '--fixture'], { encoding: 'utf8' }));
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
		for (const [name, ids] of [['transverse', ['2','5']], ['root_entity', ['2','3','1']], ['isolated', ['1','5','6']]]) {
			await page.goto('about:blank');
			await page.setContent('<style>' + css + '</style><script>var select2arrayoflanguage = {};</script><script>' + jquery + '</script><script>' + select2 + '</script>' + fixtures[name]);
			await page.waitForFunction(() => jQuery('#fk_user_driver').data('select2'));
			assert.deepEqual(await page.locator('#fk_user_driver option').evaluateAll(options => options.map(option => option.value).filter(id => Number(id) > 0)), ids);
			await page.locator('#select2-fk_user_driver-container').click();
			assert.equal(await page.locator('.select2-search__field').count(), 1);
			if (name === 'transverse') {
				await page.locator('.select2-search__field').fill('Allowed');
				await page.getByRole('option', { name: 'Allowed Driver (Enabled)', exact: true }).click();
				assert.equal(await page.locator('#fk_user_driver').inputValue(), '2');
			} else await page.keyboard.press('Escape');
		}
		await page.goto('about:blank');
		await page.setContent(fixtures.empty);
		assert.equal(await page.locator('#fk_user_driver').isDisabled(), true);
		const plain = await browser.newPage({ javaScriptEnabled: false });
		await plain.setContent(fixtures.transverse);
		await plain.selectOption('#fk_user_driver', '2');
		assert.equal(await plain.locator('#fk_user_driver').inputValue(), '2');
		assert.deepEqual(errors, []);
		console.log('5 assignment selector browser scenarios passed (native Select2, search, filtering, empty list, no JavaScript)');
	} finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
