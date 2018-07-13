![Package Logo](https://github.com/VitexSoftware/php-flexibee-matcher/blob/master/package_logo.png "Project Logo")

Párovač faktur pro FlexiBee
===========================

Instalace balíčku po spuštění (vytvoří potřebné štítky  NEIDENTIFIKOVANO a CHYBIFAKTURA) 

K dispozici jsou tři skripty na párování faktur:

    [ParujFakturyNew2Old.php](src/ParujFakturyNew2Old.php) - páruje faktury po jednotlivých dnech zpět až 3mesíce.
    [ParujVydaneFaktury.php](src/ParujVydaneFaktury.php)   - pokusí se zpárovat všechny nespárované vydané doklady
    [ParujPrijateFaktury.php](src/ParujPrijateFaktury.php) - pokusí se zpárovat všechny nespárované přijaté doklady

Algoritmus je následující:

    * stažení výpisů z banky do flexibee
    * projdou se všechny nespárované příjmy v bance ( /c/firma_s_r_o_/banka/(sparovano eq false AND typPohybuK eq 'typPohybu.prijem' AND storno eq false AND datVyst eq '2018-03-07' )?limit=0&order=datVyst@A&detail=custom:id,kod,varSym,specSym,sumCelkem,datVyst )
    * Platby se pak v cyklu po jedné zpracovávají
    * Ke každé příchozí platbě se program pokusí nalézt vhodný (neuhrazený a nestornovaný) doklad ke spárování. Nejprve podle variabilního symbolu. Nakonec dle prostého specifického symbolu.
    * Výsledky jsou sjednoceny dle čísla bankovního pohybu ve flexibee aby nedocházelo k duplicitám když faktura vyhoví více ruzným hledáním.
    * Platby které nemají dohledaný protějšek dle žádné z podmínek jsou označeny štítkem NEIDENTIFIKOVANO
    * Pokud k platbě není dohledána faktura, dostane platba štítek CHYBIFAKTURA

Dohledané doklady se pak párují takto:

    * **FAKTURA** - platba se spáruje s fakturou + uhrazená faktura je odeslána z flexibee na email klienta
    * **ZALOHA**  - zálohová faktura je spárována s platbou + je vytvořen daňový doklad se stejným variabilním symbolem od kterého je tato záloha odečtena.
    * **DOBR**    - je proveden odpočet dobropisu
    * Ostatní     - je zapsáno varování do protokolu s polu s linkem do webového flexibee


Debian/Ubuntu
-------------

Pro Linux jsou k dispozici .deb balíčky. Prosím použijte repo:

    wget -O - http://v.s.cz/info@vitexsoftware.cz.gpg.key|sudo apt-key add -
    echo deb http://v.s.cz/ stable main > /etc/apt/sources.list.d/ease.list
    apt update
    apt install php-flexibee-matcher

Po instalaci balíku jsou v systému k dispozici dva nové příkazy:

  * **php-flexibee-matcher**         - páruje všechny toho schopné faktury
  * **php-flexibee-matcher-in**      - páruje všechny toho schopné přijaté faktury
  * **php-flexibee-matcher-out**     - páruje všechny toho schopné vydané faktury
  * **php-flexibee-matcher-new2old** - páruje den po dni od nejnovějších plateb ke starším


Závislosti
----------

Tento nástroj ke svojí funkci využívá následující knihovny:

 * [**EasePHP Framework**](https://github.com/VitexSoftware/EaseFramework) - pomocné funkce např. logování
 * [**FlexiPeeHP**](https://github.com/Spoje-NET/FlexiPeeHP)        - komunikace s [FlexiBee](https://flexibee.eu/)
 * [**FlexiPeeHP Bricks**](https://github.com/VitexSoftware/FlexiPeeHP-Bricks) - používají se třídy Zákazníka, Upomínky a Upomínače


Testování:
----------

K dispozici je základní test funkcionality spustitelný příkazem **make test** ve zdrojové složce projektu

Pouze testovací faktury a platby se vytvoří příkazem **make pretest**
![Prepare](https://raw.githubusercontent.com/VitexSoftware/php-flexibee-matcher/master/doc/preparefortesting.png "Preparation")

Test sestavení balíčku + test instalace balíčku + test funkce balíčku obstarává [Vagrant](https://www.vagrantup.com/)

Konfigurace
-----------

 * [/etc/flexibee/client.json](client.json)   - společná konfigurace připojení k FlexiBee serveru
 * [/etc/flexibee/matcher.json](matcher.json) - nastavení párovače:

```
    "EASE_APPNAME": "InvoiceMatcher",             - název aplikace 
    "EASE_MAILTO": "info@yourdomain.net",         - kam odesílat reporty
    "EASE_LOGGER": "syslog|mail|console",         - jak logovat
    "PULL_BANK": "false",                         - stahnout banku před párováním
    "DAYS_BACK": "7"                              - až kolik dní zpět párovat
```


Mohlo by vás zajímat
--------------------

https://github.com/VitexSoftware/php-flexibee-reminder


Poděkování
----------

Tento software by nevznikl pez podpory:

[ ![Spoje.Net](https://raw.githubusercontent.com/VitexSoftware/php-flexibee-matcher/master/doc/spojenet.gif "Spoje.Net s.r.o.") ](https://spoje.net/)
[ ![PureHtml](https://raw.githubusercontent.com/VitexSoftware/php-flexibee-matcher/master/doc/purehtml.png "PureHTML.cz") ](http://purehtml.cz/)
[ ![Connectica](https://raw.githubusercontent.com/VitexSoftware/php-flexibee-matcher/master/doc/connectica.png "Mgr. Radek Vymazal") ](https://ictmorava.cz)

