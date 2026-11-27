// Free email relay using your Gmail. Steps:
// 1) Go to script.google.com > New project > paste this code. Change SECRET below to a long random text.
// 2) Deploy > New deployment > type: Web app > Execute as: Me > Who has access: Anyone > Deploy. Authorise when asked.
// 3) Copy the Web app URL into MAIL_WEBHOOK_URL in config.php and the same SECRET into MAIL_WEBHOOK_SECRET.
// Gmail allows roughly 100 emails per day for a normal account.
const SECRET = 'change-this-to-a-long-random-secret';
function doPost(e) {
  try {
    const d = JSON.parse(e.postData.contents);
    if (d.secret !== SECRET) return out({ ok: false, error: 'bad secret' });
    MailApp.sendEmail({ to: d.to, subject: d.subject, htmlBody: d.html, name: d.name || 'MockPrep' });
    return out({ ok: true });
  } catch (err) { return out({ ok: false, error: String(err) }); }
}
function out(o) { return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON); }
