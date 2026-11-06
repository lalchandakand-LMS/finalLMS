# Aloms English Center LMS — Laravel 12 Source Release

এই প্যাকেজে Phase 1–19-এর বর্তমান Laravel source ও integration/hardening patch একত্র করা হয়েছে। এটি এখনো production-ready বা one-click cPanel installer নয়।

## প্রয়োজনীয় পরিবেশ
- PHP 8.2+ এবং Laravel 12-এর প্রয়োজনীয় PHP extensions (বিশেষত PDO MySQL, OpenSSL, Mbstring, Tokenizer, XML, Ctype, JSON, Fileinfo, BCMath)
- MySQL 8+
- Composer 2
- HTTPS-enabled hosting এবং Laravel `storage/` ও `bootstrap/cache/`-এ write permission

## Installer প্রস্তুতি (প্রযুক্তিগত সহায়তা প্রয়োজন)
1. Composer dependencies install করে `vendor/` তৈরি করতে হবে (`composer install --no-dev --optimize-autoloader`)। এই ZIP-এ `vendor/` নেই।
2. cPanel-এ project root ও `public/` document root সঠিকভাবে আলাদা করে বসানোর পর `public/setup.php` একবার খুলুন। এটি Database connection যাচাই করে `.env`, `APP_KEY` এবং random `INSTALLER_KEY` তৈরি করে; `.setup.lock` marker-ও তখন তৈরি হয়। ZIP-এ আগাম `.setup.lock` রাখা হয় না, কারণ তা থাকলে প্রাথমিক setup page নিজেই বন্ধ হয়ে যায়।
3. Setup page-এ সফলতা দেখালে Installer Key নিরাপদে সংরক্ষণ করুন এবং `public/setup.php` মুছে ফেলুন। এই Key কাউকে পাঠাবেন না।
4. Laravel runtime সচল হলে `/install/run-migrations`-এ গিয়ে Installer Key দিয়ে migration চালান; এরপর `/install/admin`-এ প্রথম Super Admin তৈরি করুন। সফল হলে `.setup.lock` ও `.env` থেকে Installer Key সরানোর চেষ্টা করা হবে।
5. Laravel-এর `public/` directory-ই web document root হতে হবে। `.env`, source code বা `vendor/` সরাসরি public web root-এ expose করা যাবে না।

## নিরাপত্তা
- Installer Key, APP_KEY, database password, payment credentials বা API secret কারও সঙ্গে শেয়ার করবেন না।
- Setup শেষে `.setup.lock` অনুপস্থিত এবং `.env`-এ `INSTALLER_KEY` নেই—এটি যাচাই করুন।
- Production-এ `APP_DEBUG=false`, HTTPS, private payment-proof storage, নিয়মিত off-site backup এবং least-privilege DB user ব্যবহার করুন।
- Live gateway, SMS/email provider, meeting provider ও Android APK build-এর জন্য আলাদা credentials, integration ও provider sandbox tests প্রয়োজন।

## যাচাইয়ের সীমা
এই build-এ source-level/static checks করা হয়েছে। এই পরিবেশে Composer executable এবং PDO MySQL/SQLite extension অনুপস্থিত থাকায় Composer dependency resolution, Laravel runtime, database migrations, real gateway callbacks, Android integration, cPanel deployment, penetration test বা 300+ concurrent live-class load test চালানো যায়নি। তাই এই package-কে source release candidate হিসেবে ধরুন—production release নয়।

## Hosting Preflight (নতুন)
`public/preflight.php` ফাইলটি সাময়িকভাবে ব্রাউজারে খুলে PHP extension, Composer vendor, document root ও folder permission-এর প্রাথমিক অবস্থা দেখা যায়। রিপোর্ট দেখার পর ফাইলটি অবশ্যই Delete করবেন। এটি Laravel Runtime বা Database Migration Test-এর বিকল্প নয়। বিস্তারিত: `docs/HOSTING_PREFLIGHT_BN.md`।

## Phase 17 — Certificate & Payment Integrity

- সার্টিফিকেট ইস্যুর আগে Enrollment row lock করে completion state পুনরায় যাচাই করা হয়।
- Public verification response cache বন্ধ করা হয়েছে এবং revoked certificate আলাদাভাবে দেখানো হয়।
- Refund-এর পর আর কোনো verified payment না থাকলে Enrollment cancel ও Certificate revoke করা হয়।
- Android API দিয়ে শেষ Lesson complete হলেও Certificate service চালু হয়।
- বিস্তারিত: `PHASE_17_CERTIFICATE_PAYMENT_AUDIT_BN.md`।

## Phase 16 — Teacher Dashboard ও Guardian Access

- Admin/Super Admin কোর্সের বিস্তারিত পৃষ্ঠা থেকে সক্রিয় Teacher অ্যাসাইন/সরাতে পারবেন।
- Teacher Dashboard-এ শুধু নির্ধারিত কোর্স, ওই কোর্সের active student summary এবং নিজের লাইভ ক্লাস দেখানো হয়।
- Guardian Portal-এ কেবল সক্রিয় Student account-এর linked data দেখানো হয়।
- বিস্তারিত: `PHASE_16_TEACHER_GUARDIAN_SECURITY_BN.md`।

**Production status:** Phase 17-এর source-level checks ও PHP syntax checks পাস করেছে। Laravel Runtime, MySQL migration, PHPUnit, Blade compilation, browser, gateway এবং deployment পরীক্ষা সম্পন্ন না হওয়া পর্যন্ত এই সোর্সকে Production Ready ধরা যাবে না।

## Phase 18 — Production Security Audit

- সব response-এ browser security headers যোগ করা হয়েছে; HTTPS canonical URL হলে HSTS চালু হয়।
- Android API-তে প্রতি account-এ সর্বোচ্চ পাঁচটি active device token রাখা হয়; নতুন login হলে পুরোনো token revoke হয়।
- Web/API lesson elapsed-time check future-dated start timestamp গ্রহণ করে না।
- Security headers ও device-token policy-এর PHPUnit feature tests যোগ করা হয়েছে।
- বিস্তারিত: `PHASE_18_PRODUCTION_SECURITY_AUDIT_BN.md` এবং `PHASE_18_RELEASE_NOTES_BN.md`।

**Production status:** Phase 18-এর static checks ও PHP syntax lint পাস করেছে। PHPUnit, Laravel runtime, MySQL, Blade, browser, gateway ও deployment test এখনো execute হয়নি। তাই এটি এখনো Production Ready নয়।


## Phase 19 — Runtime Readiness Hardening

- Web login route-এ ৫ request/minute throttling।
- Paid course certificate issue করতে অন্তত একটি verified payment আবশ্যক; free course-এ payment আবশ্যক নয়।
- Refund ও certificate issue-এর payment/enrollment lock ordering সামঞ্জস্য করা হয়েছে।
- Web ও Android API lesson completion enrollment row lock করে serialize করে।
- নতুন PHPUnit feature tests ও static checks যোগ হয়েছে।
- Hosting preflight এখন PHP 8.3+, প্রয়োজনীয় extension, APP_DEBUG=false, APP_KEY এবং private storage exposure পরীক্ষা করে; কোনো secret value প্রকাশ করে না।
- বিস্তারিত: `PHASE_19_RUNTIME_READINESS_HARDENING_BN.md`।

**বর্তমান Release Status:** Source hardening ও static checks করা হয়েছে। Composer dependency resolution, Laravel Runtime, MySQL migration, PHPUnit, Blade compilation, browser, gateway sandbox, cPanel deployment, penetration এবং load test এখনো বাস্তবে সফলভাবে চালানো হয়নি। `vendor/` এই ZIP-এ নেই। এটি production release নয়।

## Phase 20 — Release Preflight Update

নতুন `tests/release_preflight_static.php` route controller/method ও Blade view reference-এর উপস্থিতি যাচাই করে। এটি framework-free static check; Laravel runtime test নয়। Phase 20 রিপোর্ট: `PHASE_20_RELEASE_PREFLIGHT_BN.md`। `vendor/`, `composer.lock`, MySQL runtime migration ও PHPUnit সফল না হওয়া পর্যন্ত এই ZIP production-ready নয়।
