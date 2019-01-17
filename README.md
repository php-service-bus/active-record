[![Build Status](https://travis-ci.org/mmasiukevich/active-record.svg?branch=master)](https://travis-ci.org/mmasiukevich/active-record)
[![Code Coverage](https://scrutinizer-ci.com/g/mmasiukevich/active-record/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/mmasiukevich/active-record/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mmasiukevich/active-record/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mmasiukevich/active-record/?branch=master)
[![License](https://poser.pugx.org/mmasiukevich/active-record/license)](https://packagist.org/packages/mmasiukevich/active-record)

## What is it?

Simple Active Record implementation (based on [storage component](https://github.com/mmasiukevich/storage)) for [service-bus](https://github.com/mmasiukevich/service-bus) framework.

#### Examples

@see [examples](https://github.com/mmasiukevich/storage/tree/master/examples) directory

The abstract class to be implemented contains methods:
* [new()](https://github.com/mmasiukevich/active-record/blob/master/src/Table.php#L113): Creates an object and saves a new entry to the database
* [find()](https://github.com/mmasiukevich/active-record/blob/master/src/Table.php#L150): Search record with specified ID in the database
* [findOneBy()](https://github.com/mmasiukevich/active-record/blob/master/src/Table.php#L170): Search record by specified conditions
* [findBy()](https://github.com/mmasiukevich/active-record/blob/master/src/Table.php#L212): Search records by the specified conditions
* [save()](https://github.com/mmasiukevich/active-record/blob/master/src/Table.php#L269): Save change set
* [refresh()](https://github.com/mmasiukevich/active-record/blob/master/src/Table.php#L315): Reload record
* [remove()](https://github.com/mmasiukevich/active-record/blob/master/src/Table.php#L354): Delete record
* [lastInsertId()](https://github.com/mmasiukevich/active-record/blob/master/src/Table.php#L375): Receive last insert ID
