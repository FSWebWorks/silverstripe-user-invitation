# SilverStripe User Invitation

[![Build Status](https://scrutinizer-ci.com/g/FSWebWorks/silverstripe-user-invitation/badges/build.png?b=master)](https://scrutinizer-ci.com/g/FSWebWorks/silverstripe-user-invitation/build-status/master)
[![scrutinizer](https://scrutinizer-ci.com/g/fswebworks/silverstripe-user-invitation/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/fswebworks/silverstripe-user-invitation/)
[![Code Coverage](https://codecov.io/gh/fswebworks/silverstripe-user-invitation/branch/master/graph/badge.svg)](https://codecov.io/gh/fswebworks/silverstripe-user-invitation)
[![License](http://img.shields.io/packagist/l/fswebworks/silverstripe-user-invitation.svg?style=flat-square)](LICENSE.md)

## Introduction

This module adds the ability to invite users to a secure website (e.g. Intranet or Extranet).

## Requirements

 * SilverStripe 3.6+

## Features

* Quick-entry invitation form (By default only first name and email fields are required to invite someone)
* Sends email invitations to recipient
* Supports optional user group aqssignment (See below for how to enforce this group selection) 
* Invitation expiry can be set via configuration.
* Default SilverStripe member validation is applied.

### Force required user group assignment
Place the following in your mysite/_config/config.yml
```yml
UserInvitation:
    force_require_group: true
```

## Installation

 ```sh
 $ composer require fswebworks/silverstripe-user-invitation
 ```

## Maintainer

Franco Springveldt - franco@fswebworks.co.za

## License

This module is licensed under the [MIT license](LICENSE).
