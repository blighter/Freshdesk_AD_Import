# Freshdesk_AD_Import
Import users into Freshdesk from Active Directory
Tested with Apache 2.4.4 with PHP 5.6.3

##Prerequisites
- Web server running PHP
- Enable the php_imap extension in php.ini (make sure to copy /php/libsasl.dll to /apache/bin)
- Access to Freshdesk API key (can be found in your profile settings on Freshdesk)

##Installation
- Copy import.php to your web server
- Create a /images/ directory
- Configure settings at the top of the PHP file
- Create a customer field in Freshdesk named "Office"

##Executing
- Navigate to /import.php?email=youremail@domain.com
- Any errors will be displayed in the browser and logged to /log.txt

##Coming Soon
- Bulk Import
- User selection by OU/filters
- Support for custom Freshdesk fields

##Important Notes
###Activation Emails
Activation emails are a requirement of Freshdesk and any user you create via this method will receive one.
###Bulk Import
For now you could do a bulk import by using a batch script:
- Create a one column CSV with email addresses of your users
- Use the below code in a .bat file
- This is not recommended for >100 users

for /f "usebackq tokens=1 delims=," %%a in ("csv.csv") do (start "" http://webserver/upload.php?email=%%a)
