# Lonely Planet Weather #

This is simple plugin using to display current weather from OpenWeatherMap API

## Requirements: ##

* [Node.js](http://nodejs.org/)
* [Compass](http://compass-style.org/)
* [GIT](http://git-scm.com/)
* [Composer](https://getcomposer.org/)
* [Grunt](http://gruntjs.com/)

## Installation: ##

Clone this repo:

```bash
$ git clone git@github.com:tranthienbinh1989/lp-weather.git
```

Install vendor using composer

```bash
$ composer install
```

Install the dependencies of the grunt:

```bash
$ npm install
```

Finally rename the files as you want and create your GIT repository.

## Commands: ##

Lint, compile and compress the files:

```bash
$ grunt
```

Watch the project:

```bash
$ grunt watch
```

Deploy with svn:

```bash
$ grunt deploy
```

Running test

```bash
$ vendor/bin/phpunit
```

##### 1.0.0 #####

* Initial version.
