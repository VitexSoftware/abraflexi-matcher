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

For Linux, .deb packages are available from two repositories, depending on how up to date vs. how stable you need the package to be:

* **[repo.vitexsoftware.com](https://repo.vitexsoftware.com/)** - test/nightly channel. Publishes automatically on every merge to `main`; newest features and fixes land here first, but it hasn't been through a formal release.
* **[repo.multiflexi.eu](https://repo.multiflexi.eu/)** - production channel (release/security). Only tagged releases are published here; use this for production deployments.

Test/nightly (repo.vitexsoftware.com):

```sh
wget -qO- https://repo.vitexsoftware.com/keyring.gpg | sudo tee /etc/apt/trusted.gpg.d/vitexsoftware.gpg
echo "deb [signed-by=/etc/apt/trusted.gpg.d/vitexsoftware.gpg]  https://repo.vitexsoftware.com  $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
sudo apt update
sudo apt install abraflexi-matcher
```

Production (repo.multiflexi.eu):

```sh
wget -qO- https://repo.multiflexi.eu/KEY.gpg | sudo tee /etc/apt/trusted.gpg.d/multiflexi.gpg
echo "deb [signed-by=/etc/apt/trusted.gpg.d/multiflexi.gpg]  https://repo.multiflexi.eu  $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/multiflexi.list
sudo apt update
sudo apt install abraflexi-matcher
```

After installing the package, the following new commands are available in the system:

* **[abraflexi-matcher](debian/abraflexi-matcher.1)** - matches all capable invoices.
* **[abraflexi-matcher-in](debian/abraflexi-matcher-in.1)** - matches all capable received invoices.
* **[abraflexi-matcher-out](debian/abraflexi-matcher-out.1)** - matches all capable issued invoices (all matching methods combined).
* **[abraflexi-matcher-new2old](debian/abraflexi-matcher-new2old.1)** - matches incoming payments day by day from the newest to the oldest.
* **[abraflexi-matcher-init](debian/abraflexi-matcher-init.1)** - creates the AbraFlexi labels/document types required by the matcher scripts.
* **[abraflexi-pull-bank](debian/abraflexi-pull-bank.1)** - only downloads bank statements.
* **[abraflexi-match-bank](debian/abraflexi-match-bank.1)** - matches incoming payments.
* **[abraflexi-match-cash](debian/abraflexi-match-cash.1)** - legacy variant matching issued invoices via AbraFlexi's own bank-matching logic.
* **[abraflexi-match-received-payment](debian/abraflexi-match-received-payment.1)** - matches one specific incoming payment on demand.
* **[abraflexi-match-varsym](debian/abraflexi-match-varsym.1)** - matches issued invoices against received payments by variable symbol only.
* **[abraflexi-match-specsym](debian/abraflexi-match-specsym.1)** - matches issued invoices against received payments by specific symbol only.
* **[abraflexi-match-accountno](debian/abraflexi-match-accountno.1)** - matches issued invoices against received payments by the sender's bank account number only.
* **[abraflexi-transaction-report](debian/abraflexi-transaction-report.1)** - generates bank transaction reports in JSON format.

Overpayments and underpayments are never settled automatically by the `abraflexi-match-varsym`, `abraflexi-match-specsym`, `abraflexi-match-accountno`, `abraflexi-matcher-out`, `abraflexi-match-received-payment`, and `abraflexi-matcher-in` scripts - a mismatch between the paid amount and the invoice amount is only logged and reported (`overpaid`/`underpaid` in the JSON report), and the invoice is left open for manual review by accounting staff. Set `ABRAFLEXI_OVERPAY` / `ABRAFLEXI_PARTIAL_MATCH` to opt back into automatic settlement of overpayments / underpayments.

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

Configuration is read from environment variables (or a `.env` file next to the scripts - see [example.env](example.env); `/etc/abraflexi/client.json` / `/etc/abraflexi/matcher.json` are **no longer used**):

```
   APP_NAME=InvoiceMatcher                          - application name
   EASE_MAILTO=your@email.tld                       - where to send reports
   EASE_LOGGER=console|syslog                        - how to log
   MATCHER_DAYS_BACK=300                             - how many days back to match
   MATCHER_LABEL_PREPLATEK=OVERPAYMENT               - label for marking more than the required amount for the paid invoice
   MATCHER_LABEL_CHYBIFAKTURA=MISSINGINVOICE         - label for marking payment for which no invoice was found
   MATCHER_LABEL_NEIDENTIFIKOVANO=UNIDENTIFIED       - label for marking payments that could not be identified at all
   ABRAFLEXI_OVERPAY='OST. ZÁVAZKY'                  - code of document type for overpayment, empty (default) = do not settle overpayments automatically
   ABRAFLEXI_PARTIAL_MATCH=false                     - settle underpayments (partial payments) automatically, default false = do not settle automatically
   MATCHER_LOCALIZE=en_US                            - language for messages and logs, default en_US, available en_US|cs_CZ|sk_SK
```

Languages
---------

The source language of messages and logs is English (`en_US`); translations are available for Czech (`cs_CZ`) and Slovak (`sk_SK`) — see [i18n](i18n). The language is set via the `MATCHER_LOCALIZE` variable (available as a select field on each application in MultiFlexi).

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

| | Application | Command |
|---|---|---|
| ![icon](multiflexi/ad0d8e27-7763-4a87-9d4a-68c5cdf7930f.svg?raw=true) | Bank Statements Puller | `abraflexi-pull-bank` |
| ![icon](multiflexi/dd22e4a1-58b3-4e4b-b832-ad65ebcf4dc9.svg?raw=true) | Transaction Report | `abraflexi-transaction-report` |
| ![icon](multiflexi/67bf1b78-6885-4169-984c-84b1b2761429.svg?raw=true) | Received Invoices Matcher | `abraflexi-matcher-in` |
| ![icon](multiflexi/177888a6-56aa-4024-89ec-80743150502a.svg?raw=true) | Issued Invoices Matcher | `abraflexi-matcher-out` |
| ![icon](multiflexi/23bf774d-de12-44b7-b4ef-454dd11ed8fd.svg?raw=true) | Payment Matcher (single payment) | `abraflexi-match-received-payment` |
| ![icon](multiflexi/0085bf8e-a682-46d8-9419-fc296ba26173.svg?raw=true) | Match Received Payments by Variable Symbol | `abraflexi-match-varsym` |
| ![icon](multiflexi/deadb87e-826e-41a1-b2fd-7b46f45503c2.svg?raw=true) | Match Received Payments by Specific Symbol | `abraflexi-match-specsym` |
| ![icon](multiflexi/16100625-6140-41f5-8b76-d411a1b82bb6.svg?raw=true) | Match Received Payments by Bank Account Number | `abraflexi-match-accountno` |

## Flowcharts

Decision flow of the three split payment matchers (what parameters they read and how they branch):

### abraflexi-match-varsym

![Variable symbol matcher flowchart](docs/diagrams/match-varsym-flow.svg)

### abraflexi-match-specsym

![Specific symbol matcher flowchart](docs/diagrams/match-specsym-flow.svg)

### abraflexi-match-accountno

Matches domestic payments by account number + bank code (`buc`/`smerKod`) and foreign payments by IBAN, flagging ambiguous account numbers found registered to more than one company as `duplicate_buc` in the report.

![Bank account number matcher flowchart](docs/diagrams/match-accountno-flow.svg)

## Exit Codes

Applications in this package use the following exit codes:

### Transaction Report (abraflexi-transaction-report)

- `0`: Success - transactions report generated successfully
- `1`: General error - unexpected error occurred (retryable)
- `2`: Connection error - unable to connect to AbraFlexi server (critical, retryable)
- `3`: I/O error - failed to write output file

### Other Applications

- `0`: Success
- `400`: Bad request - invalid data or parameters

## Error Handling

The transaction report application includes robust error handling:

- **Connection failures**: When the AbraFlexi server is unreachable, the application logs a detailed error message and exits with code 2, allowing for automatic retry.
- **MultiFlexi compliance**: Generates reports in MultiFlexi-compliant JSON format with status, timestamp, metrics, and artifacts.
- **Graceful degradation**: All errors are properly caught, logged, and reported with appropriate exit codes.
