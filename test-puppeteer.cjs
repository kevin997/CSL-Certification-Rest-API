const puppeteer = require('puppeteer');

(async () => {
    try {
        console.log('Launching browser...');
        const browser = await puppeteer.launch({
            args: ['--no-sandbox', '--disable-setuid-sandbox'],
            headless: "new"
        });
        const page = await browser.newPage();
        await page.setContent('<h1>Hello World</h1>');
        await page.pdf({ path: 'test.pdf', format: 'A4' });
        await browser.close();
        console.log('PDF generated successfully');
    } catch (error) {
        console.error('Error generating PDF:', error);
        process.exit(1);
    }
})();
