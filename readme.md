# tk-database 

__Project:__ tk-database  
__Web:__ <http://www.database.com/>  
__Authors:__ Michael Mifsud <http://www.tropotek.com/>  

  
A database lib for the Tropotek tk library.

## Contents

- [Installation](#installation)
- [Introduction](#introduction)
- [Upgrade](#upgrade)

## Installation

Available on Packagist ([uom/tk-database](http://packagist.org/packages/uom/tk-database))
and installable via [Composer](http://getcomposer.org/).

```bash
composer require uom/tk-database
```

Or add the following to your composer.json file:

```json
"uom/tk-database": "~3.2.0"
```


## Introduction




## Upgrade

If you have DB migration issues you may manually update some system tables. 
See if you have any table without the underscore and rename them to the following.

```mysql
-- NOTE: This has to be run manually before upgrading to ver 3.2
RENAME TABLE _migration TO _migration;
RENAME TABLE _data TO _data;
RENAME TABLE session TO _session;
RENAME TABLE _plugin TO _plugin;
```
Also check your src/config/application.php file and ensure that there are no manual
overrides for this table as you may get unexpected results

