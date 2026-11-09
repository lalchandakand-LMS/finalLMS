# নিরাপদ আপডেট, ব্যাকআপ ও রিকভারি নির্দেশিকা

## বর্তমান অ্যাপ্লিকেশন সুবিধা

- Admin > System health & updates পেজে PHP, production/debug configuration, database connection, migration table, writable directories এবং private backup archive-এর অবস্থা দেখা যায়।
- Admin > Backup & restore থেকে database, media এবং assignment-submission file-এর signed private archive তৈরি ও ডাউনলোড করা যায়।
- Restore চালানোর আগে সিস্টেম বর্তমান অবস্থার একটি আলাদা `pre-restore-safety` backup তৈরি করে। Safety backup তৈরি না হলে restore শুরু করা যাবে না।
- Backup archive-এর HMAC signature যাচাই করা হয়। নতুন archive-এ schema fingerprint থাকে; অন্য schema/version-এর backup restore করতে গেলে তা প্রত্যাখ্যান করা হয়।
- Restore-এর জন্য Admin password এবং স্পষ্ট `RESTORE` confirmation বাধ্যতামূলক।

## Release update করার নিরাপদ পদ্ধতি

এই সংস্করণে arbitrary ZIP upload করে এক ক্লিকে core code overwrite করার সুবিধা ইচ্ছাকৃতভাবে চালু করা হয়নি। Signed release manifest, trusted signing key, atomic release-directory switch এবং rollback runner ছাড়া এমন updater চালু করলে attacker বা corrupted ZIP দিয়ে সম্পূর্ণ LMS takeover হতে পারে।

প্রতিটি update-এর আগে:
1. System health পেজে সব গুরুত্বপূর্ণ check পর্যালোচনা করুন।
2. Backup & restore থেকে নতুন backup তৈরি করে ডাউনলোড করুন।
3. সফল CI run-এর নির্দিষ্ট commit-এর release artifact সংগ্রহ করুন।
4. আলাদা staging domain ও database-এ install/upgrade, login, course learning, payment record, media এবং backup restore পরীক্ষা করুন।
5. Production-এ বর্তমান `.env` ও private uploads অপরিবর্তিত রেখে release deploy করুন।
6. কেবল release-এর documented migrations চালান; বিদ্যমান LMS-এ fresh installer চালাবেন না।
7. Login, course, enrollment, lesson, payment, admin pages এবং application logs পরীক্ষা করে তারপর maintenance window বন্ধ করুন।
8. নতুন release যাচাই না হওয়া পর্যন্ত আগের code package ও matching database backup সংরক্ষণ করুন।

## গুরুত্বপূর্ণ সীমাবদ্ধতা ও লাইভ লঞ্চের শর্ত

- একই hosting account-এ রাখা backup off-site disaster recovery নয়। Backup-এর দ্বিতীয় কপি আলাদা নিরাপদ storage/account-এ রাখতে হবে।
- cPanel-এ বাস্তব restore rehearsal না হওয়া পর্যন্ত backup-কে verified disaster-recovery solution বলা যাবে না।
- Schema fingerprint কেবল database table/column structure মিলিয়ে দেখে; application-code compatibility বা file-system rollback নিশ্চিত করে না।
- Update dashboard এখন health/readiness ও নিরাপদ release নির্দেশনা দেয়; এটি স্বয়ংক্রিয়ভাবে GitHub থেকে release download/deploy করে না।
- Production launch-এর আগে staging subdomain, পৃথক test database এবং cPanel access দিয়ে বাস্তব পরীক্ষা আবশ্যক।
