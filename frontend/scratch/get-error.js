const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  
  const errors = [];
  page.on('pageerror', err => {
    errors.push('PageError: ' + err.toString());
  });
  
  page.on('console', msg => {
    if (msg.type() === 'error') {
      errors.push('Console Error: ' + msg.text());
    }
  });

  try {
    await page.goto('http://localhost:5173/register/supervisor?token=izL9s6GG4PbLAzJZHIGM28Ql5X2txV01ip3iWcBDgAw4bS3G', { waitUntil: 'networkidle0', timeout: 10000 });
  } catch (e) {
    console.log("Goto error:", e.toString());
  }
  
  console.log("ERRORS DETECTED:");
  if (errors.length === 0) {
    console.log("No errors found in console!");
  } else {
    errors.forEach(e => console.log(e));
  }
  
  await browser.close();
})();
