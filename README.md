![Package Logo](abraflexi-matcher.svg?raw=true "Project Logo")

Invoice Matcher for AbraFlexi
=============================

Package installation after running (creates necessary labels UNIDENTIFIED and MISSINGINVOICE)

There are three scripts available for invoice matching:

[ParujFakturyNew2Old.php](src/ParujFakturyNew2Old.php) - matches invoices day by day up to 3 months back.
[ParujVydaneFaktury.php](src/ParujVydaneFaktury.php) - attempts to match all unmatched issued documents.
[ParujPrijateFaktury.php](src/ParujPrijateFaktury.php) - attempts to match all unmatched received documents.
[ParujPrijatouBanku.php](src/ParujPrijatouBanku.php) - attempts to match suitable invoices to the given incoming payment.

The algorithm is as follows:

  * Download bank statements to abraflexi.
  * All unmatched receipts in the bank are processed ( /c/company_ltd_/bank/(matched eq false AND movementType eq 'movementType.receipt' AND cancellation eq false AND issueDate eq '2018-03-07' )?limit=0&order=issueDate@A&detail=custom:id,code,varSym,specSym,totalSum,issueDate ).
  * Payments are then processed one by one in a loop.
  * For each incoming payment, the program tries to find a suitable (unpaid and uncanceled) document to match. First by variable symbol. Finally by simple specific symbol.
  * Results are unified by bank movement number in abraflexi to avoid duplicates when an invoice meets multiple search criteria.
  * Payments that do not have a counterpart found by any condition are labeled UNIDENTIFIED.
  * If an invoice is not found for the payment, the payment is labeled MISSINGINVOICE.

Matched documents are then paired as follows:

  * **INVOICE** - the payment is matched with the invoice + the paid invoice is sent from abraflexi to the client's email.
  * **ADVANCE** - the advance invoice is matched with the payment + a tax document with the same variable symbol is created from which this advance is deducted.
  * **CREDIT** - the credit note is deducted.
  * Others - a warning is logged in the protocol along with a link to the web abraflexi.

Debian/Ubuntu
-------------

For Linux, .deb packages are available. Please use the repo:

   wget -qO- https://repo.vitexsoftware.com/keyring.gpg | sudo tee /etc/apt/trusted.gpg.d/vitexsoftware.gpg
   echo "deb [signed-by=/etc/apt/trusted.gpg.d/vitexsoftware.gpg]  https://repo.vitexsoftware.com  $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
   sudo apt update
   sudo apt install abraflexi-matcher

After installing the package, the following new commands are available in the system:

  * **abraflexi-matcher** - matches all capable invoices.
  * **abraflexi-matcher-in** - matches all capable received invoices.
  * **abraflexi-matcher-out** - matches all capable issued invoices.
  * **abraflexi-matcher-new2old** - matches incoming payments day by day from the newest to the oldest.
  * **abraflexi-pull-bank** - only downloads bank statements.
  * **abraflexi-match-bank** - matches incoming payments.

Dependencies
------------

This tool uses the following libraries for its functionality:

 * [**EasePHP Framework**](https://github.com/VitexSoftware/php-ease-core) - helper functions such as logging.
 * [**AbraFlexi**](https://github.com/Spoje-NET/AbraFlexi) - communication with [AbraFlexi](https://abraflexi.eu/).
 * [**AbraFlexi Bricks**](https://github.com/VitexSoftware/AbraFlexi-Bricks) - classes for Customer, Reminders, and Reminder.

Testing:
--------

Basic functionality testing is available and can be run with the command **make test** in the project's source folder.

Test invoices and payments can be created with the command **make pretest**.
![Prepare](https://raw.githubusercontent.com/VitexSoftware/php-abraflexi-matcher/master/doc/preparefortesting.png "Preparation")

Package build + package installation test + package function test is handled by [Vagrant](https://www.vagrantup.com/).

Configuration
-------------

 * [/etc/abraflexi/client.json](client.json) - common configuration for connecting to the AbraFlexi server.
 * [/etc/abraflexi/matcher.json](matcher.json) - matcher settings:

```
   "APP_NAME": "InvoiceMatcher",             - application name
   "EASE_MAILTO": "info@yourdomain.net",     - where to send reports
   "EASE_LOGGER": "syslog|mail|console",     - how to log
   "PULL_BANK": "false",                     - download bank before matching
   "DAYS_BACK": "7"                          - how many days back to match
   "MATCHER_LABEL_PREPLATEK": "OVERPAYMENT", - label for marking more than the required amount for the paid invoice
   "MATCHER_LABEL_CHYBIFAKTURA": "MISSINGINVOICE", - label for marking payment for which no invoice was found
   "MATCHER_LABEL_NEIDENTIFIKOVANO": "UNIDENTIFIED" -       
```

Other software for AbraFlexi
----------------------------

 * [Regular reports from AbraFlexi](https://github.com/VitexSoftware/AbraFlexi-Digest)
 * [Reminder sender](https://github.com/VitexSoftware/php-abraflexi-reminder)
 * [Client Zone for AbraFlexi](https://github.com/VitexSoftware/AbraFlexi-ClientZone)
 * [Tools for testing and managing AbraFlexi](https://github.com/VitexSoftware/AbraFlexi-TestingTools)
 * [Monitoring AbraFlexi server function](https://github.com/VitexSoftware/monitoring-plugins-abraflexi)
 * [AbraFlexi server without graphical dependencies](https://github.com/VitexSoftware/abraflexi-server-deb)

Acknowledgements
----------------

This software would not have been created without the support of:

[ ![Spoje.Net](https://raw.githubusercontent.com/VitexSoftware/php-abraflexi-matcher/master/doc/spojenet.gif "Spoje.Net s.r.o.") ](https://spoje.net/)
[ ![PureHtml](https://raw.githubusercontent.com/VitexSoftware/php-abraflexi-matcher/master/doc/purehtml.png "PureHTML.cz") ](http://purehtml.cz/)
[ ![Connectica](https://raw.githubusercontent.com/VitexSoftware/php-abraflexi-matcher/master/doc/connectica.png "Mgr. Radek Vymazal") ](https://cnnc.cz)

MultiFlexi
----------

AbraFlexi Matcher is ready to run as a [MultiFlexi](https://multiflexi.eu) application.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/apps.php)

