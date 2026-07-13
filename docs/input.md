# Input

[← Reference](README.md)

Typed, safe reading of HTTP request data. The core reads a key from a source array, coerces it with [`Rak200\Utils\Filter`](https://github.com/rak200/utils/blob/master/docs/filter.md), and returns a caller-supplied default when the key is missing or the value cannot be represented. Convenience shortcuts read a string from the matching superglobal.

```php
use Rak200\HttpInput\Input;
```

## Contents

- [`str`](#str)
- [`int`](#int)
- [`float`](#float)
- [`bool`](#bool)
- [`array`](#array)
- [`has`](#has)
- [`all`](#all)
- [`get` / `post` / `request` / `cookie` / `server` / `env`](#get--post--request--cookie--server--env)

---

## `str`

Reads `$key` from `$source` as a string (coerced via `Filter::toStr`), or `$default` when the key is absent or the value cannot be coerced (e.g. an array).

```php
$source = ['name' => 'Ada', 'age' => 42, 'tags' => ['a']];

Input::str($source, 'name');            // 'Ada'
Input::str($source, 'age');             // '42'   (coerced)
Input::str($source, 'tags');            // null   (array → uncoercible)
Input::str($source, 'missing', 'def');  // 'def'
```

[↑ Back to top](#input)

---

## `int`

Reads `$key` as an int (via `Filter::toInt`), or `$default`. Optional `$min`/`$max` clamp the result (each bound applied independently); a null result is never clamped.

```php
$source = ['page' => '3', 'over' => '500', 'bad' => 'x'];

Input::int($source, 'page');                       // 3
Input::int($source, 'bad', 1);                     // 1   (uncoercible → default)
Input::int($source, 'missing', 1);                 // 1
Input::int($source, 'page', 1, min: 1);            // 3
Input::int($source, 'over', 1, min: 1, max: 100);  // 100 (clamped)
```

[↑ Back to top](#input)

---

## `float`

Reads `$key` as a float (via `Filter::toFloat`), or `$default`. Optional `$min`/`$max` clamp the result.

```php
$source = ['price' => '9.99', 'bad' => 'x'];

Input::float($source, 'price');                        // 9.99
Input::float($source, 'bad', 0.0);                     // 0.0
Input::float($source, 'price', null, min: 0.0, max: 5.0);   // 5.0 (clamped)
```

[↑ Back to top](#input)

---

## `bool`

Reads `$key` as a bool (via `Filter::toBool`, which understands HTML-form values), or `$default`. `"1"`, `"true"`, `"on"`, `"yes"` are true; `"0"`, `"false"`, `"off"`, `"no"`, `""` are false.

```php
$source = ['remember' => 'on', 'subscribe' => '0', 'weird' => 'maybe'];

Input::bool($source, 'remember');         // true
Input::bool($source, 'subscribe');        // false
Input::bool($source, 'weird');            // null
Input::bool($source, 'missing', false);   // false
```

[↑ Back to top](#input)

---

## `array`

Reads `$key` as an array (e.g. a `name[]` field), or `$default` when the key is absent or the value is not an array. Elements are not coerced.

```php
$source = ['tags' => ['a', 'b'], 'name' => 'Ada'];

Input::array($source, 'tags');          // ['a', 'b']
Input::array($source, 'name');          // null  (scalar → not an array)
Input::array($source, 'missing', []);   // []
```

[↑ Back to top](#input)

---

## `has`

Returns true if `$key` is present in `$source` — including when its value is null.

```php
$source = ['present' => null, 'value' => 1];

Input::has($source, 'present');   // true
Input::has($source, 'missing');   // false
```

[↑ Back to top](#input)

---

## `all`

Returns `$source` unchanged — a readable way to name "the whole request bag".

```php
Input::all($_POST);   // the $_POST array
```

[↑ Back to top](#input)

---

## `get` / `post` / `request` / `cookie` / `server` / `env`

Convenience shortcuts that read a string from the matching superglobal (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`, `$_ENV`), or `$default` when absent.

```php
Input::get('q');                    // ?string from $_GET
Input::post('name', 'Anonymous');   // ?string from $_POST
Input::request('id');               // $_REQUEST
Input::cookie('session');           // $_COOKIE
Input::server('HTTP_HOST');         // $_SERVER
Input::env('APP_ENV');              // $_ENV
```

For a typed read from a superglobal, call the core directly — there is no `getInt`/`postBool` proliferation:

```php
$page     = Input::int($_GET, 'page', 1, min: 1);
$remember = Input::bool($_POST, 'remember', false);
```

[↑ Back to top](#input)
