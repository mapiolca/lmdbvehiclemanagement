/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
// Synthetic local HTML; native Dolibarr Form, jQuery UI, Select2 and date helpers.
// node test/run_qualification_modal_browser.cjs <Dolibarr htdocs>
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const core = path.resolve(process.argv[2]);
const read = file => fs.readFileSync(path.join(core, file), 'utf8');
const fixtures = JSON.parse(execFileSync('php', [path.join(__dirname, 'run_qualification_modal.php'), core, '--fixture'], { encoding: 'utf8' }));
const head = read('core/js/lib_head.js.php');
const dateHelpers = head.slice(head.indexOf('function getObjectFromID('), head.indexOf('function urlencode('));
assert.ok(!dateHelpers.includes('<?php'));
const scripts = ['includes/jquery/js/jquery.min.js', 'includes/jquery/js/jquery-ui.min.js', 'includes/jquery/plugins/select2/dist/js/select2.full.js'].map(read);
const css = ['includes/jquery/css/base/jquery-ui.css', 'includes/jquery/plugins/select2/dist/css/select2.css'].map(read).join('\n');
const modal = fs.readFileSync(path.join(__dirname, '../js/regulatory_qualification.js'), 'utf8');
(async () => {
	const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined });
	try {
		const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
		page.setDefaultTimeout(10000);
		const errors = [];
		page.on('pageerror', error => errors.push(error.message));
		async function load(name) {
			await page.goto('about:blank');
			await page.setContent('<style>' + css + '</style><script>var select2arrayoflanguage = {};</script>' + scripts.map(script => '<script>' + script + '</script>').join('') + '<script>' + dateHelpers + '</script>' + fixtures[name] + '<script>' + modal + '</script>');
			await page.waitForFunction(() => document.querySelector('#regulatory-qualification[data-modal="0"]') || jQuery('#regulatory-qualification').hasClass('ui-dialog-content'));
		}
		await load('confirmed');
		assert.equal(await page.locator('#regulatory-qualification').isVisible(), false);
		assert.match(await page.locator('body').innerText(), /Confirmée/);
		await page.getByRole('link', { name: 'Visualiser / éditer', exact: true }).click();
		assert.equal(await page.getByRole('dialog').isVisible(), true);
		// Native Select2 remains searchable inside the modal.
		await page.locator('#select2-answer_choice_1-container').click();
		await page.locator('.select2-search--dropdown .select2-search__field').fill('Non');
		await page.getByRole('option', { name: 'Non', exact: true }).click();
		assert.equal(await page.locator('#answer_choice_1').inputValue(), '3');
		await page.locator('#manual_profile_ids + .select2 .select2-search__field').fill('Profil B');
		await page.getByRole('option', { name: 'Profil B', exact: true }).click();
		// Native datepicker, including split fields submitted to the server.
		await page.locator('.ui-datepicker-trigger').click();
		await page.locator('#ui-datepicker-div').getByRole('link', { name: '15', exact: true }).click();
		assert.equal(await page.locator('#answer_date_1day').inputValue(), '15');
		await page.locator('#answer_date_1').fill('16/09/2026');
		await page.locator('#answer_date_1').press('Tab');
		await page.locator('form').evaluate(form => form.addEventListener('submit', event => { event.preventDefault(); window.submittedQualification = Array.from(new FormData(form)); }));
		await page.getByRole('button', { name: 'Enregistrer la qualification', exact: true }).click();
		const submitted = await page.evaluate(() => window.submittedQualification);
		for (const [name, value] of [['id', '42'], ['action', 'save_qualification'], ['token', 'qualification-fixture-token'], ['answer_date_1day', '16'], ['answer_date_1month', '9'], ['answer_date_1year', '2026'], ['answer_choice_1', '3']]) {
			assert.equal(submitted.find(field => field[0] === name)?.[1], value, name);
		}
		assert.deepEqual(submitted.filter(field => field[0] === 'manual_profile_ids[]').map(field => field[1]), ['10', '11']);
		await page.getByRole('link', { name: 'Fermer', exact: true }).click();
		assert.equal(await page.getByRole('dialog').isVisible(), false);
		await page.locator('#regulatory-qualification-open').click();
		await page.keyboard.press('Escape');
		assert.equal(await page.getByRole('dialog').isVisible(), false);
		await load('readonly');
		await page.getByRole('link', { name: 'Visualiser', exact: true }).click();
		assert.match(await page.getByRole('dialog').innerText(), /Oui/);
		assert.match(await page.getByRole('dialog').innerText(), /12\/01\/2026/);
		assert.equal(await page.getByRole('dialog').locator('form, input, select').count(), 0);
		for (const name of ['incomplete', 'missing_date', 'empty']) {
			await load(name);
			assert.equal(await page.getByRole('dialog').count(), 0);
			assert.equal(await page.locator('#regulatory-qualification').isVisible(), true);
			assert.match(await page.locator('body').innerText(), /À confirmer/);
		}
		await load('failed');
		assert.equal(await page.getByRole('dialog').isVisible(), true);
		assert.equal(await page.locator('#answer_date_1').inputValue(), '16/09/2026');
		for (const width of [768, 390]) {
			await page.setViewportSize({ width, height: 844 });
			await page.waitForFunction(() => jQuery('#regulatory-qualification').dialog('option', 'width') === window.innerWidth - 32);
			const box = await page.getByRole('dialog').boundingBox();
			assert.ok(box.width <= width && box.height <= 844, JSON.stringify(box));
		}
		const plain = await browser.newPage({ javaScriptEnabled: false });
		await plain.setContent(fixtures.confirmed);
		assert.equal(await plain.locator('#regulatory-qualification').isVisible(), false);
		assert.match(await plain.locator('#regulatory-qualification-open').getAttribute('href'), /show_qualification=1/);
		await plain.setContent(fixtures.fallback);
		assert.equal(await plain.locator('#regulatory-qualification').isVisible(), true);
		assert.deepEqual(errors, []);
		console.log('7 qualification scenarios passed: native modal, Select2, dates, POST, readonly, incomplete, failed save; tablet/phone sizing and no-JavaScript fallback checked');
	} finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
