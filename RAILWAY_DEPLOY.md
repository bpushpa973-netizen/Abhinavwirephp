ABHINAV MULTIPANEL — RAILWAY AUTO DATABASE SETUP

1. Deploy this ZIP/project to your Railway web service.
2. Confirm the MySQL connection variables are set on the web service:
   MYSQLHOST, MYSQLPORT, MYSQLDATABASE, MYSQLUSER, MYSQLPASSWORD
3. Open: https://YOUR-DOMAIN/install.php
4. Enter the admin username, email and a new password (minimum 8 characters).
5. Press "Create Database & Admin". The installer creates all 7 required tables:
   users, mods, license_keys, mod_apks, referral_codes, transactions, user_sessions
6. After the success message, remove install.php from the deployed project.
7. Log in using the admin username and password you just created.

No SQL import or manual table creation is required. The installer uses CREATE TABLE IF NOT EXISTS.
Keep your database password private. Do not share screenshots that expose it.
