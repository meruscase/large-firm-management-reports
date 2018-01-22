# large-firm-management-reports
Real world billing and cash flow reports for multi-office firms with hundreds of users

## cases-last-billed.php
A report of all cases that have been billed on, with a column for the most recent ledger item on that case.

_Written using PHP 5.6 but it should work with anything greater._

#### Run Server in directory:
```bash
php -S localhost:1337
```
And navigate to [http://localhost:1337/cases-last-billed.php](http://localhost:1337/cases-last-billed.php) and enter your MerusCase credentials to get a CSV

#### Debug with PHPScript
```bash
php cases-last-billed.php username=username@example.com password=badPassword!
```
_Note: Plus signs (+) must be passed into PHP with `%2B`_