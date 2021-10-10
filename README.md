# LaraHook - A Hook engine for Laravel

This is a maintained fork of the now inactive [esemve/Hook](https://github.com/esemve/Hook) library for laravel 8.   

In most cases this library can be used as a drop in replacement, although some changes may require minor updates to your code. Please see [the differences section](#differences-between-this-and-esemvehook) for more information on the changes between this library and `esemve/Hook`

### What is this?

Hooks allow programmers to make parts of their application open to being modified or adjusted from other locations within the code base. For example allowing an application modify the behavior of your package without them needing directly edit its source code.

### What is a Hook?

It is similar to an event. A code bounded by a hook runs unless a hook listener catches it and orders that instead of the function in the hook, something else should run. They could be set in an established order, so you are able to make several modifications in the code.

### What is it good for?

Example 1: You have a module which displays an editor. This remains the same editor in every case.
If you bound the display of the editor in a hook, then you can write a module which can redefine/override this hook, and for example changs the textarea to a ckeditor.

Example 2: You list the users. You can include every line's print in a hook. This way you can write a separate module which could extend this line with an e-mail address print.

Example 3: You save the users' data in a database. If you do it in a hook, you can write a module which could add extra fields to the user model like "first name" or "last name". To do that, you didn't need to modify the code that handles the users, the extension module doesn't need to know the functioning of the main module.


... and so many other things. If you are building a CMS-like system, it will make your life a lot easier.

# How do I install it?

```bash
composer require coinvestor/larahook
```

then to the app.php :
```php
...
'providers' => [
    ...
    CoInvestor\LaraHook\HookServiceProvider::class,
    ...
 ],
 'aliases' =>[
    ...
    'Hook' => CoInvestor\LaraHook\Facades\Hook::class
    ...
 ]
```

# How does it work?

## Methods

### Get
Run a hook and return some data. Listeners for the hook will be triggered and the final result from these will be returned.
```
Hook::get(
    string $hook,                    // Name of the hook to run
    array $params,                   // Array of values to be passed to the listeners on this hook.
    callable $callback,              // Callback method to return default value if no listeners are registered
    bool $useCallbackAsFirstListener // Should the default callback be run and used as the first $output value for the listeners. Defaults to false.
);
```

### listen
Listen for the hook and either carry out an action or manipulated the output before it is returned.
```
Hook::listen(
    $hook,            // Name of the hook to listen on.
    function          // Callback to execute when hook is run.
    (
        $callback,    // Default callback from the `get`. Called as `$callback->call` 
        $output,      // Output from previous hook. Will be null unless useCallbackAsFirstListener is set to true, in which case it will be the default callbacks value
        $arg1,        // Args as passed in to the `get` methods params.
        $arg2,
        ...
    )
    $priority         // Used the control when each listener runs. The higher this value, the later it will run in the list of listeners on the hook.
);
```

## Simple example

```php
$user = new User();
$user = Hook::get('fillUser', [$user], function($user) {
    return $user;
}, true);
```

In this case a fillUser hook is thrown, which receive the $user object as a parameter. If nothing catches it, the internal function, the return $user will run, so nothing happens. But it can be caught by a listener from a provider:

```php
Hook::listen('fillUser', function ($callback, $output, $user) {
    $output->profileImage = ProfileImage::getForUser($user->id);
    return $output;
}, 10);

```
The $callback contains the hook's original internal function, so it can be called here.

Multiple listeners could be registered to a hook, so in the $output the listener receives the response of the previously registered listeners of the hook.

THen come the parameters delivered by the hook, in this case the user.

The hook listener above caught the call of the fillUser, extended the received object, and returned it to its original place. After the run of the hook the $user object contains a profileImage variable as well.

Number 10 in the example is the priority. They are executed in an order, so if a number 5 is registered to the fillUser as well, it will run before number 10.

## Initial output

By passing `true` as the 4th paramater to the get you can ensure the default callback
provided will always run, passing its self as the initial output value.

```php
Hook::get('testing', ['Delilah'], function ($testString) {
    return 'Hi ' . $testString;
}, true)

// and later ...

Hook::listen('testing', function ($callback, $output, $name) {
    if ($output === 'Hi Delilah') {
        $output = "{$output}. Whats up!";
    } else {
        $output = "{$output}. Welcome back.";
    }
    return $output; // 'Hi Delilah. Whats up!'
});
```

If there is no listeners, 'Hi Delilah' will be returned.

## Usage in blade templates

```php
@hook('hookName')
```

In this case the hook listener can catch it like this:
```php
 Hook::listen('template.hookName', function ($callback, $output, $variables) {
     return view('test.button');
 });
```
In the $variables variable it receives all of the variables that are available for the blade template.

:exclamation: **To listen blade templates you need to listen `template.hookName` instead of just `hookName`!**

## Wrap HTML
```php
@hook('hookName', true)
    this content can be modified with dom parsers
    you can inject some html here
@endhook
```
Now the `$output` parameter contains html wrapped by hook component.
```php
Hook::listen('template.hookName', function ($callback, $output, $variables) {
    return "<div class=\"alert alert-success\">$output</div>";
});
```

## Stop
```php
Hook::stop();
```
Put in a hook listener it stops the running of the other listeners that are registered to this hook.


## For testing

```php
Hook::mock('hookName','returnValue');
```
After that the hookName hook will return returnValue as a response.

# Artisan

```bash
php artisan hook::list
```

Lists all the active hook listeners.

# Differences between this and `esemve/Hook`

* The InitialContent on `get` has been replaced by the ``useCallbackAsFirstListener` flag. Setting this will return the result of the default callback as the initial `$option` value.
* Listeners on the same hook at the same priority will no longer overwrite each other. Listerners at the same priority will run in the order you register them. 
* Returning a falsey value from a listeners will now return that value directly, rather than causing the default callback to be returned instead.
* Compatibility with laravel 8+
* `getListeners` can now return listeners for a specified hook. Results will always be an array.
* Caller information now includes file & line numbers.
* Added `removeListener` and `removeListeners` methods.


---
License: MIT
