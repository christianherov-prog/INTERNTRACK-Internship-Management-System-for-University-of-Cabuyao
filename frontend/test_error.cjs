const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  
  page.on('console', msg => console.log('PAGE LOG:', msg.text()));
  page.on('pageerror', error => console.log('PAGE ERROR:', error.message));

  await page.goto('http://localhost:5173', { waitUntil: 'networkidle2' });
  
  try {
    await page.type('input[type="email"]', 'jdelacruz@mail.com'); // standard test student
    await page.type('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'networkidle2' });
  } catch(e) {}

  console.log("Going to portfolio...");
  await page.goto('http://localhost:5173/student/portfolio', { waitUntil: 'networkidle2' });
  await page.waitForTimeout(2000);
  console.log("Done.");
  await browser.close();
})();
