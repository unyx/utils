# nyx/utils
[![Latest Stable Version](https://poser.pugx.org/nyx/utils/v/stable.png)](https://packagist.org/packages/nyx/utils)
[![Total Downloads](https://poser.pugx.org/nyx/utils/downloads.png)](https://packagist.org/packages/nyx/utils)
[![Build Status](https://travis-ci.org/unyx/utils.png)](https://travis-ci.org/unyx/utils)
[![Dependency Status](https://www.versioneye.com/user/projects/52754de0632bac61f8000087/badge.png)](https://www.versioneye.com/user/projects/52754de0632bac61f8000087)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/unyx/utils/badges/quality-score.png?s=2327a52d17cb074b3bf8652dc5429ae49a688d53)](https://scrutinizer-ci.com/g/unyx/utils/)

-----

Contains various utilities which are aimed at common day-to-day operations within PHP - dealing with arrays, strings,
the filesystem etc.

All classes in this package are static by design. Aside from how you would normally extend a static class, all of them
can also be dynamically extended with methods (names -> callables) at runtime if you want to use them at the core
of a larger project where others might want to provide additional functionality as well.

> #### Note: In development (read: unusable)
> It is currently in its **design** phase and therefore considered **unusable**. The API will fluctuate, solar flares will
> appear, wormholes will consume your data, gremlins will chase your cat. You've been warned.

-----

### Requirements

- PHP 7.0+

Specific classess may have specific requirements outlined in their descriptions (see `utils\Str` for an example).
Dependency checks are **not** put in place to keep the overhead as small as possible.

### Installation

The only supported way of installing this package is using [Composer](http://getcomposer.org).

- Add `nyx/utils` as a dependency to your project's `composer.json` file.
- Run `composer update` and you're ready to go.

### Documentation

The code is fully inline documented for the time being. Online documentation will be made available in due time.

### Credits

The `When` utility is largely based on [react/promise](https://github.com/reactphp/promise) which is licensed under
the MIT license and (c) 2012 [Jan Sorgalla](https://github.com/jsor).

### License

Nyx is open source software licensed under the [MIT license](http://opensource.org/licenses/MIT).
