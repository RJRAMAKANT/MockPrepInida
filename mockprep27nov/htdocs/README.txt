MockPrep India - PHP/MySQL (InfinityFree ready)
SETUP
1. InfinityFree: create a MySQL database (Control Panel > MySQL Databases). Note host, db name, user, password.
2. Edit config.php: database, MAIL_FROM (an address on your domain), ADMIN_DIR.
3. Rename folder mp-control-7x4k to something secret and set ADMIN_DIR to the same name.
4. Upload everything to htdocs. Subfolder? set BASE.
5. Open /install.php, create the admin account. A verification link is shown on screen (and emailed). Click it, then DELETE install.php.
6. Links: Students /   Teachers /teacher/   Admin /<ADMIN_DIR>/
QUESTION BANK
- Admin > Syllabus & Coverage: every exam has its own subjects and topics (starter syllabus included - match it to the official notification). Topics with 0 questions are shown in red.
- Admin > Questions > Upload CSV: columns exam,subject,topic,question,A,B,C,D,answer(A-D),explanation. See sample_questions.csv. Tick "setname" to also create a practice set.
- Admin > Practice sets: build fixed sets from the bank by exam / subject / topic.
- Students: Topic Practice gives only the chosen topic's questions. After every test they see answers + explanations, and can reopen them from the dashboard (Answers button).
EMAIL (SMTP)
- In config.php set SMTP_HOST/PORT/SECURE/USER/PASS and MAIL_FROM. Free providers: Brevo (smtp-relay.brevo.com, 587, tls - verify your sender first) or Gmail (smtp.gmail.com, 587, tls, with an App Password).
- If InfinityFree blocks SMTP ports, set MAIL_API_KEY (Brevo API key) to send over HTTPS instead.
- Admin > Dashboard > Send test email shows the exact error. Admin > Users has a Verify button as a safety net.
- Verification and result emails use PHP mail(). Free hosting may delay or spam-folder them. Use Admin > Dashboard > Send test email to check. If mail never arrives, set REQUIRE_VERIFY false in config.php.
UPGRADING an earlier install: delete install.lock, open install.php again (existing data is kept; new tables/columns are added).

EXAM PATTERN
- Admin > Syllabus: set subjects, questions per subject and full-mock time per exam. Clone an exam (e.g. OAVS-CS -> OAVS-MATHS) and change only the subject-related section.
- Admin > Mock / Practice sets: pick an exam, adjust the per-subject counts (e.g. English 20, Odia 20, Reasoning 30, Current Affairs 30, subject 100) and create the set.
AI QUESTIONS (free keys): set AI_PROVIDER/AI_KEY/AI_MODEL in config.php. Gemini (Google AI Studio) or Groq/OpenRouter (provider 'openai' + AI_URL). Always review generated questions.

NEW IN THIS VERSION
- Clean URLs (/login, /register, /exam/OAVS-CS, /sitemap.xml, /robots.txt) via .htaccess. In a subfolder, uncomment RewriteBase in .htaccess.
- CBT exam screen: instructions, section tabs, question palette (not visited / not answered / answered / marked), Mark for Review, Clear, Save & Next, auto-submit, section-wise result.
- Mock sets: Admin > Mock / Practice sets lets you choose time, marks, negative marking and how many questions from every subject and topic.
- EMAIL: if SMTP is blocked, set MAIL_API_KEY (Brevo API key, free) - it sends over HTTPS. While email is not working, MAIL_FALLBACK_LINK shows the verification link on screen; set it to false once email works. Admin > Users also has a Verify button.

TROUBLESHOOTING: open /check.php first. It shows PHP version, database connection, missing tables, .htaccess and install status. Delete check.php and install.php when everything is green. Set DEBUG to false in config.php when live.

EMAIL FIX FOR FREE HOSTING (recommended): free Gmail relay
1. Open script.google.com > New project > paste mail-relay.gs. Change SECRET to a long random text.
2. Deploy > New deployment > Web app > Execute as: Me, Who has access: Anyone > Deploy. Authorise.
3. Put the Web app URL in MAIL_WEBHOOK_URL and the same secret in MAIL_WEBHOOK_SECRET (config.php).
4. Admin > Dashboard > Send test email. Verification, password-reset and result emails now go through your Gmail (about 100 per day).
NEW: Admin > Notice board (scrolling 'What's new' strip on the home page and dashboards), forgot/reset password, student profile page.
