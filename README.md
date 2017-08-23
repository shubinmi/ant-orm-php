# Ant ORM 
## Easy way to manipulate your data

The concept - to know its place and purpose.
The rule of this ORM - to be flexible and not to be intrusive.

## Installation

Install the latest version with

```bash
$ composer require shubinmi/ant-orm-php
```

## Basic Usage

See at tests folder

## Contribute safely

Be in sure that nothing is broken

### MySql
- Init db by

```bash
php ./tets/Mysql/init/init.php -h 127.0.0.1 -u root -p root
```
- Run tests by

```bash
./vendor/phpunit/phpunit/phpunit --bootstrap ./tests/Mysql/boot.php ./tests/Mysql/
```

