# nyx/utils
[![Latest Stable Version](https://poser.pugx.org/nyx/utils/v/stable.png)](https://packagist.org/packages/nyx/utils)
[![Total Downloads](https://poser.pugx.org/nyx/utils/downloads.png)](https://packagist.org/packages/nyx/utils)
[![Build Status](https://travis-ci.org/unyx/utils.png)](https://travis-ci.org/unyx/utils)
[![Dependency Status](https://www.versioneye.com/user/projects/55c5433165376200200034e2/badge.png)](https://www.versioneye.com/user/projects/55c5433165376200200034e2)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/unyx/utils/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/unyx/utils)
[![License](http://img.shields.io/:license-mit-blue.svg)](http://alcore.mit-license.org)

-----

![Dev warning](http://s7.postimg.org/6cruwesi3/Nyx.png)

Contains various utilities which are aimed at common day-to-day operations within PHP - dealing with arrays, strings,
the filesystem etc.

All classes in this package are static by design. Aside from how you would normally extend a static class, all of them
can also be dynamically extended with methods (names -> callables) at runtime if you want to use them at the core
of a larger project where others might want to provide additional functionality as well.

-----

### Requirements

- PHP 7.1.0+

Specific classes may have specific requirements outlined in their descriptions (see `utils\Str` for an example).
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

Nyx is open source software licensed under the [MIT license](http://alcore.mit-license.org).
