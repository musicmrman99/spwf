<?php
/**
 * @see Dispatcher
 */

namespace dispatcher;

use util\Util;
use dispatcher\exceptions\UndispatchableError;

/**
 * Behaves like the object it wraps (passes all method calls to the object),
 * except that if invoked, it will call the wrapper given in the constructor.
 * 
 * NOTE: Wrapper objects are not equivalent to wrapped objects with regard to
 * nominal typing (whether static or dynamic). The wrapper is unlikely to
 * implement the same interfaces as the wrapped object. This has multiple
 * effects, such as that `instanceof` will likely return unexpected results.
 * Nonetheless, the wrapper object is guaranteed to function the same as the
 * wrapped object at runtime (apart from when it is invoked).
 * 
 * @author William Taylor (19009576)
 */
class DelegateFuncObj {
    private $obj;
    private $wrapper;

    /**
     * Initialise the dispatcher.
     * 
     * @param mixed $obj The object to wrap.
     * @param callable $wrapper The function to use for __invoke().
     */
    public function __construct($obj, $wrapper) {
        $this->obj = $obj;
        $this->wrapper = $wrapper;
    }

    /**
     * Delegate to the wrapper for any invocations.
     * 
     * @param array<mixed> $args Arguments to pass to the delegated wrapper.
     * @return mixed Whatever the wrapper returns.
     */
    public function __invoke(...$args) {
        $wrapper = $this->wrapper; // PHP <=5.6
        return $wrapper(...$args);
    }

    /**
     * Delegate to the object for any method calls.
     * 
     * @param string $name The name of the method to call.
     * @param array<mixed> $args An array of arguments to pass to that method.
     */
    public function __call($name, $args) {
        return call_user_func_array([$this->obj, $name], $args);
    }
}

/**
 * Acts as a key-value store for callables and allows calling them by key.
 * 
 * A range of both ordinary and static factory methods are also available for:
 * - Common map structure needs (getting some or all keys, values, and mappings)
 * - Assisting with manipulating candidate keys (those that could be dispatched
 *   to at a later date)
 * - Various common algorithms for deciding on one or more keys to dispatch to,
 *   and generating closures for dispatching to them.
 * 
 * @author William Taylor (19009576)
 */
class Dispatcher {
    const AUTO_DEFAULT_KEY = PHP_INT_MAX;

    private $map;
    private $defaultKey;

    /* Construction and Registration
    -------------------------------------------------- */

    /**
     * Create a new dispatcher.
     * 
     * @param array<mixed,callable> $map (Optional) The initial dispatch map.
     * @param mixed $defaultKey (Optional) The global default key for this
     *   dispatcher. If this is Dispatcher::AUTO_DEFAULT_KEY, then the key used
     *   for the first callable registered to this dispatcher (in constructor or
     *   with register()) will be used as the default key.
     */
    public function __construct($map = null, $defaultKey = null) {
        $this->map = isset($map) ? $map : [];

        $this->defaultKey = $defaultKey;
        if ($this->defaultKey === self::AUTO_DEFAULT_KEY && !$this->isEmpty()) {
            reset($map);
            $this->defaultKey = key($this->map);
        }
    }

    /**
     * Register a callable to a key.
     * 
     * If the the default key was set to Dispatcher::AUTO_DEFAULT_KEY when the
     * Dispatcher was created, then the first callable to be registered
     * (possibly the first key in the initial map) will become the default key.
     * 
     * @param mixed $key (Optional) The key that will be dispatched to the
     *   callable.
     * @param callable $fn The callable that will be dispatched to for that key.
     */
    public function register($param1, $param2 = null) {
        list($key, $fn) = Util::optionalFirstParam($param1, $param2);

        if ($this->defaultKey === self::AUTO_DEFAULT_KEY && $this->isEmpty()) {
            if ($key === null) {
                $this->defaultKey = 0;
            } else {
                $this->defaultKey = $key;
            }
        }

        if ($key === null) {
            $this->map[] = $fn;
        } else {
            $this->map[$key] = $fn;
        }
    }

    /* Utils
    -------------------------------------------------- */

    /**
     * Return whether the dispatch mapping is empty.
     * 
     * @return bool Whether the dispatch mapping is empty.
     */
    public function isEmpty() {
        return count($this->map) === 0;
    }

    /**
     * Return all keys registered in this dispatcher.
     * 
     * @return array<mixed> All keys registered in this dispatcher.
     */
    public function getKeys() {
        return array_keys($this->map);
    }

    /**
     * Return all callables registered in this dispatcher.
     * 
     * @param array<mixed> $keys (Optional) A list of keys to get the values
     *   for. Defaults to all keys in the map.
     * @return array<callable> Some (if keys are given) or all callables
     *   registered in this dispatcher.
     */
    public function getValues($keys = null) {
        if ($keys === null) {
            return array_values($this->map);
        }

        return Util::mapValues($keys, function ($key) {
            return $this->map[$key];
        });
    }

    /**
     * Return all [key => callable] pairs registered in this dispatcher.
     * 
     * @param array<string> $keys (Optional) A list of keys to get the
     *   [key => callable] mappings for. Defaults to all keys in the map.
     * @return array<callable> Some (if keys are given) or all [key => callable]
     *   pairs registered in this dispatcher.
     */
    public function getMap($keys = null) {
        if ($keys === null) {
            return $this->map; // Array, so copy
        }

        return array_reduce($keys, function ($map, $key) {
            $map[$key] = $this->map[$key];
            return $map;
        }, []);
    }

    /**
     * Get the real key or keys for the given simple key.
     * 
     * Whether this function returns a single key or an array of keys depends on
     * the mutators given. See their documentation for details.
     * 
     * This involves putting the given simple key through each of the given
     * mutators in turn. Various mutators are defined by Dispatcher that allow
     * special behaviour.
     * 
     * The returned key has some properties worthy of note:
     * - It may or may not be a valid key for this dispatcher.
     * - Subsequent calls to realKey with the same key and mutators may produce
     *   different real key(s), as mutators may not be pure functions. In fact,
     *   many useful mutators defined in Dispatcher are not pure functions.
     * 
     * @param mixed $key A simple key for which a valid real key for this map
     *   may be found.
     * @return mixed Whatever the last mutator used returns.
     */
    public function realKey($key, $mutators) {
        if ($mutators === null) $mutators = [];
        elseif (!is_array($mutators)) $mutators = [$mutators];

        return array_reduce(
            $mutators,
            function ($accum, $mutator) {
                return $mutator($accum);
            },
            $key
        );
    }

    /**
     * A mutator for {@see realKey()} that calls the key as a function if it is
     * one and mutates to that function's return value.
     * 
     * If the key is not a function, do not mutate.
     * 
     * @param array<mixed> $params (Optional) An array of arguments to pass to
     *   the key if it's a function. Defaults to no parameters.
     */
    public function funcKeyMutator($params = null) {
        if ($params === null) $params = [];
        return function ($key) use ($params) {
            if (is_callable($key)) {
                return $key(...$params);
            }
            return $key;
        };
    }

    /**
     * A mutator for {@see realKey()} that mutates to all keys in the dispatcher
     * that match the simple key (which is treated as a regex).
     * 
     * Keys in the dispatcher are treated as literal keys, even if they are
     * valid regexes.
     */
    public function registeredMatchesKeyMutator() {
        return function ($key) {
            return Util::filterValues(
                $this->getKeys(),
                function ($registeredKey) use ($key) {
                    return preg_match("|^$key$|", $registeredKey);
                },
                false
            );
        };
    }

    /**
     * A mutator for {@see realKey()} that mutates to all keys in the dispatcher
     * (which are treated as regexes) that the simple key matches.
     * 
     * The simple key is treated as a literal key, even if it's a valid regex.
     */
    public function keyMatchesRegisteredMutator() {
        return function ($key) {
            return Util::filterValues(
                $this->getKeys(),
                function ($registeredKey) use ($key) {
                    return preg_match("|^$registeredKey$|", $key);
                },
                false
            );
        };
    }

    /**
     * A mutator for {@see realKey()} that mutates to the first key in the list
     * of keys the simple key has been mutated to, or the given default object
     * if the simple key has been mutated to an empty array.
     * 
     * Assumes the simple key has been mutated to an array of keys.
     * 
     * @param mixed $default The object to mutate to if the simple key has been
     *   mutated to an empty array (of keys).
     */
    public function firstOrDefaultMutator($default) {
        return function ($keys) use ($default) {
            if (count($keys) > 0) {
                return $keys[0];
            } else {
                return $default;
            }
        };
    }

    /* Dispatch
    -------------------------------------------------- */

    /**
     * Return whether the given key can be dispatched to.
     * 
     * @param mixed $key The key to check for, or a function that takes the
     *   given parameters and returns the key to check for.
     * @param array<mixed> $params (Optional) An array of arguments to pass to
     *   the key. Defaults to no parameters.
     * 
     * @return bool Whether the key can be dispatched to.
     */
    public function isDispatchable($key, $params = null) {
        $key = $this->realKey($key, $this->funcKeyMutator($params));
        return $key !== null && array_key_exists($key, $this->map);
    }

    /**
     * The internal dispatch function.
     * 
     * @param mixed $key The key to dispatch to, or a callable to invoke to get
     *   the key to dispatch to.
     * @param array<mixed> $params (Optional) An array of arguments to pass to
     *   the key. Defaults to no parameters.
     */
    private function dispatch($key, $params = null) {
        $key = $this->realKey($key, $this->funcKeyMutator($params));

        if ($params === null) $params = [];
        if (is_array($params)) {
            return $this->map[$key](...$params);
        } else {
            return $this->map[$key]($params);
        }
    }

    /* Standard Dispatchers
    -------------------------------------------------- */

    /**
     * Dispatch to the given key with the given parameters.
     * 
     * @param mixed $key The index or other key to dispatch to, or a callable to
     *   invoke to get the key to dispatch to. Can be null, which will dispatch
     *   to the first default key found.
     * @param array<mixed> $params (Optional) An array to pass to the
     *   dispatched-to callable as parameters (they will be unpacked). If the
     *   key is a function, these will be passed to that function as well.
     * @param mixed $defaultKey (Optional) The default key to dispatch to if the
     *   given key has not been registered in the dispatcher. This takes
     *   precedence over the dispatcher's global default key.
     * 
     * @return mixed Whatever the function dispatched to returns.
     * 
     * @throws UndispatchableError if the key is not found and neither of the
     *   default keys are dispatchable. The given default key is checked first,
     *   then the global default key.
     */
    public function toKey($key, $params = null, $defaultKey = null) {
        return $this->toFirst([$key], $params, $defaultKey);
    }

    /**
     * Dispatch to the first dispatchable registered key.
     * 
     * @param array<mixed> $keys (Optional) An ordered list of possible keys to
     *   dispatch to. Each key may be a callable to invoke to get the key to
     *   dispatch to for that element. Each real key that is null is skipped. If
     *   not given or null, then all registered keys are checked in the order
     *   they were registered.
     * @param array<mixed> $params (Optional) An array to pass to the
     *   dispatched-to callable as parameters (they will be unpacked). If the
     *   key is a function, these will be passed to that function as well.
     * @param mixed $defaultKey (Optional) The default key to dispatch to if
     *   none of the keys have been registered in the dispatcher. This takes
     *   precedence over the dispatcher's global default key.
     * 
     * @return mixed Whatever the function dispatched to returns.
     * 
     * @throws UndispatchableError if none of the requested keys (or all
     *   registered keys, if $keys is not given or null) are dispatchable and
     *   neither the given default key or global default key is dispatchable.
     *   The given default key is checked first, then the global default key.
     */
    public function toFirst($keys = null, $params = null, $defaultKey = null) {
        if ($keys === null) $keys = array_keys($this->map);
        if ($params === null) $params = [];

        foreach ($keys as $key) {
            // If $key is a function, don't call it twice
            $key = $this->realKey($key, $this->funcKeyMutator($params));
            if ($this->isDispatchable($key, $params)) {
                return $this->dispatch($key, $params);
            }
        }

        if ($this->isDispatchable($defaultKey)) {
            return $this->map[$defaultKey](...$params);
        }

        if ($this->isDispatchable($this->defaultKey)) {
            return $this->map[$this->defaultKey](...$params);
        }

        throw new UndispatchableError();
    }

    /**
     * Dispatch to all dispatchable registered keys.
     * 
     * @param array<mixed> $keys (Optional) An ordered array of all keys to
     *   dispatch to. Each key may be a callable to invoke to get the key to
     *   dispatch to for that element. For each real key that is null, the first
     *   dispatchable default key will be dispatched to. If not given or null,
     *   then all registered keys are dispatched to in the order they were
     *   registered.
     * @param array<mixed> $params (Optional) An array to pass to the
     *   dispatched-to callables as parameters (they will be unpacked). If the
     *   key is a function, these will be passed to that function as well.
     * @param mixed $retKeys (Optional) If given, only return the value(s)
     *   returned by the dispatched-to callables with the corresponding key(s).
     *   May be one of:
     *   - A single key, in which case return only the value returned by the
     *     dispatched-to callable with that key, or null if that key is not in
     *     $keys (or is not registered if $keys is not given).
     *   - An array of keys, in which case return a mapping with all keys in the
     *     intersection of $keys and $retKeys mapped to the return values of
     *     their corresponding dispatched-to callables.
     *   - Not given or null, in which case return the same as if an array of
     *     all registered keys was given.
     * @param mixed $defaultKey (Optional) The default key to dispatch to if any
     *   of the keys has not been registered in the dispatcher. This takes
     *   precedence over the dispatcher's global default key.
     * 
     * @return mixed See the $retKeys parameter.
     * 
     * @throws UndispatchableError if any key is not found and neither of the
     *   default keys are dispatchable. The given default key is checked first,
     *   then the global default key.
     */
    public function toAll(
            $keys = null,
            $params = null,
            $defaultKey = null,
            $retKeys = null
    ) {
        if ($keys === null) $keys = array_keys($this->map);
        if ($retKeys === null) $retKeys = $keys;

        $keysFnMap = [];
        foreach ($keys as $key) {
            $keysFnMap[$key] = isset($this->map[$key]) ?
                $this->map[$key] :
                null; // Not found - use defaultKey, or global defaultKey
        }

        $keysFnMap = Util::mapKeysValues(
            $keysFnMap,
            function ($key, $fn) use ($params, $defaultKey) {
                return $this->toKey($key, $params, $defaultKey);
            }
        );

        if (is_array($retKeys)) {
            return Util::filterKeysValues(
                $keysFnMap,
                function ($key, $value) use ($retKeys) {
                    return array_key_exists($key, $retKeys);
                }
            );
        }

        if (array_key_exists($retKeys, $keysFnMap)) {
            return $keysFnMap[$retKeys];
        }

        return null;
    }

    /**
     * Dispatch to all dispatchable registered keys, taking the return value of
     * each call and passing it as parameters to the next call.
     * 
     * If any dispatched call returns an array, it will be unpacked (extracted
     * as arguments) into the next call. If you need to pass along an array
     * object as a single parameter, then wrap the return value in another
     * array.
     * 
     * @param array<mixed> $keys (Optional) An ordered list of all keys to
     *   dispatch to, passing the return value of each dispatch as input to the
     *   next dispatch. Each key may be a callable to invoke to get the key to
     *   dispatch to for that element. Each real key may be null, in which case
     *   the first dispatchable default key will be dispatched to for that
     *   element. If not given or null, then all registered keys are dispatched
     *   to in the order they were registered.
     * @param array<mixed> $params (Optional) An array to pass to the first
     *   dispatched-to callable in the pipeline as parameters (they will be
     *   unpacked). If the key is a function, these will be passed to that
     *   function as well.
     * @param mixed $defaultKey (Optional) The default key to dispatch to if any
     *   of the keys has not been registered in the dispatcher. This takes
     *   precedence over the dispatcher's global default key.
     * 
     * @return mixed Whatever the last callable in the pipeline returns.
     * 
     * @throws UndispatchableError if any key is not found and neither of the
     *   default keys are dispatchable. The given default key is checked first,
     *   then the global default key.
     */
    public function toPipe($keys = null, $params = null, $defaultKey = null) {
        if ($keys === null) $keys = array_keys($this->map);

        $key = array_shift($keys);
        if ($key !== null) {
            $ret = $this->toKey($key, $params, $defaultKey);
        }
        foreach ($keys as $key) {
            $ret = $this->toKey($key, $ret, $defaultKey);
        }
        return $ret;
    }

    /* Dispatcher Function Generators
    -------------------------------------------------- */

    /**
     * Internal function to get the object to delegate to for a particular key.
     * 
     * @param mixed $delegateKey The delegate key to get the object for.
     * @return callable The delegate object for that key.
     * @throws UndispatchableError if the real key for the given delegate key is
     *   not null, but is otherwise undispatchable.
     */
    private function getDelegateObject($delegateKey) {
        $realKey = $this->realKey($delegateKey, $this->funcKeyMutator());
        if ($realKey === null) return null;

        if (!$this->isDispatchable($realKey)) {
            throw new UndispatchableError(
                "Key of pass-through object must exist in dispatcher"
            );
        }
        return $this->map[$realKey];
    }

    /**
     * Return a callable that dispatches to the given key in the dispatcher.
     * 
     * The returned callable will dispatch to the given key, taking the same
     * parameters and returning the same value as the callable with that key.
     * 
     * If the given key isn't dispatchable in this Dispatcher at the time the
     * returned callable is called, then the returned callable will dispatch to
     * the first dispatchable default key. This means the callable with the
     * given key, as well as any default keys (if it's unknown whether the given
     * key exists at the time the function is called), should have a function
     * signature compatible with the one expected by the users of the returned
     * function.
     * 
     * @param mixed $key The index or other key that the returned callable will
     *   dispatch to, or a callable to invoke to get that key. If null, the
     *   returned callable will dispatch to the first default key found.
     * @param mixed $defaultKey (Optional) The default key to dispatch to if the
     *   given key has not been registered in the dispatcher. This takes
     *   precedence over the dispatcher's global default key.
     * @param mixed $delegateKey (Optional) If given, the returned callable
     *   object will also pass any method calls directly to the dispatchable
     *   object associated with that key. The dispatch key does not need to be
     *   the same as the delegate key.
     * 
     * @return callable A callable that dispatches to the given key (or first
     *   dispatchable default key if the given key is not dispatchable), and
     *   that delegates method calls if a delegate key is given.
     * 
     * @throws UndispatchableError if the real key for the delegate key is not
     *   null, but is otherwise undispatchable.
     * 
     * @see toKey()
     * @see DelegateFuncObj
     */
    public function funcToKey($key, $defaultKey = null, $delegateKey = null) {
        return new DelegateFuncObj(
            $this->getDelegateObject($delegateKey),
            function (...$args) use ($key, $defaultKey) {
                return $this->toKey($key, $args, $defaultKey);
            }
        );
    }

    /**
     * Return a callable that dispatches to the first key in the dispatcher.
     * 
     * The returned callable will dispatch to the first dispatchable key, or the
     * first dispatchable key of the keys given (if given), taking the same
     * parameters and returning the same value as the callable with that key.
     * 
     * If the given key isn't dispatchable at the time(s) the returned callable
     * is called, then the returned callable will dispatch to the first
     * dispatchable default key. This means all callables in the dispatcher, or
     * all callables with the given keys, as well as any default keys (if it's
     * unknown whether none of the requested keys exist at the time the function
     * is called), must have a function signature compatible with the one
     * expected by the users of the returned function.
     * 
     * @param array<mixed> $keys (Optional) An ordered list of possible keys
     *   for the returned callable to dispatch to. Each key may be a callable to
     *   invoke to get the key to dispatch to for that element. Each real key
     *   that is null is skipped. If not given or null, then all registered keys
     *   are checked in the order they were registered.
     * @param mixed $defaultKey (Optional) The default key to dispatch to if the
     *   given key has not been registered in the dispatcher. This takes
     *   precedence over the dispatcher's global default key.
     * @param mixed $delegateKey (Optional) If given, the returned callable
     *   object will also pass any method calls directly to the dispatchable
     *   object associated with that key. The dispatch key does not need to be
     *   the same as the delegate key.
     * 
     * @return callable A callable that dispatches to the first dispatchable key
     *   of those registered/requested (or first dispatchable default key if
     *   none of the registered/requested keys are dispatchable), and that
     *   delegates method calls if a delegate key is given.
     * 
     * @throws UndispatchableError if the real key for the delegate key is not
     *   null, but is otherwise undispatchable.
     * 
     * @see toFirst()
     * @see DelegateFuncObj
     */
    public function funcToFirst(
            $keys = null,
            $defaultKey = null,
            $delegateKey = null
    ) {
        return new DelegateFuncObj(
            $this->getDelegateObject($delegateKey),
            function (...$args) use ($keys, $defaultKey) {
                return $this->toFirst($keys, $args, $defaultKey);
            }
        );
    }

    /**
     * Return a callable that dispatches to all keys in the dispatcher.
     * 
     * The returned callable will dispatch to all dispatchable keys, or all
     * dispatchable keys given (if given), taking the same parameters and
     * returning the same value as the callables with those keys.
     * 
     * If any of the given keys aren't dispatchable at the time(s) the returned
     * callable is called, then for each key that isn't dispatchable, the
     * returned callable will dispatch to the first dispatchable default key.
     * This means all callables in the dispatcher, or all callables with the
     * given keys, as well as any default keys (if it's unknown whether all of
     * the requested keys exist at the time the function is called), must have a
     * function signature compatible with the one expected by the users of the
     * returned function.
     * 
     * @param array<mixed> $keys (Optional) An ordered array of all keys for the
     *   returned callable to dispatch to. Each key may be a callable to invoke
     *   to get the key to dispatch to for that element. For each real key that
     *   is null, the returned callable will dispatch to the first dispatchable
     *   default key. If not given or null, then all registered keys are
     *   dispatched to in the order they were registered.
     * @param mixed $defaultKey (Optional) The default key to dispatch to if the
     *   given key has not been registered in the dispatcher. This takes
     *   precedence over the dispatcher's global default key.
     * @param mixed $retKeys (Optional) If given, the returned callable will
     *   only return the value(s) returned by the dispatched-to callables with
     *   the corresponding key(s). May be one of:
     *   - A single key, in which case the returned callable will return only
     *     the value returned by the dispatched-to callable with that key, or
     *     null if that key is not in $keys (or is not registered if $keys is
     *     not given).
     *   - An array of keys, in which case the returned callable will return a
     *     mapping with all keys in the intersection of $keys and $retKeys
     *     mapped to the return values of their corresponding dispatched-to
     *     callables.
     *   - Not given or null, in which case return the same as if an array of
     *     all registered keys was given.
     * @param mixed $delegateKey (Optional) If given, the returned callable
     *   object will also pass any method calls directly to the dispatchable
     *   object associated with that key. The dispatch key does not need to be
     *   the same as the delegate key.
     * 
     * @return callable A callable that dispatches to every dispatchable key of
     *   those registered/requested (or first dispatchable default key for any
     *   of the registered/requested keys that are not dispatchable), and that
     *   delegates method calls if a delegate key is given.
     * 
     * @throws UndispatchableError if the real key for the delegate key is not
     *   null, but is otherwise undispatchable.
     * 
     * @see toAll()
     * @see DelegateFuncObj
     */
    public function funcToAll(
            $keys = null,
            $defaultKey = null,
            $retKeys = null,
            $delegateKey = null
    ) {
        return new DelegateFuncObj(
            $this->getDelegateObject($delegateKey),
            function (...$args) use ($keys, $defaultKey, $retKeys) {
                return $this->toAll($keys, $args, $defaultKey, $retKeys);
            }
        );
    }

    /**
     * Return a callable that dispatches to all dispatchable registered keys,
     * taking the return value of each call and passing it as parameters to the
     * next call.
     * 
     * The returned callable will dispatch to each registered key, or each of
     * the keys given (if given), with the first key (or the default key if the
     * first key is undispatchable) dispatched with all arguments the returned
     * callable was given, and each subsequent key being dispatched with the
     * return value of the dispatch before it (unpacked if it returns an array).
     * The returned callable will return the same value as the last dispatched
     * callable.
     * 
     * If you need to pass along an array object as a single parameter for a
     * particular callable, then the previous callable in the pipeline must wrap
     * its return value in another array.
     * 
     * If any key in the pipeline isn't dispatchable at the time(s) the returned
     * callable is called, then the returned callable will dispatch to the first
     * dispatchable default key. This means all callables in the dispatcher, or
     * all callables with the given keys, as well as any default keys (if it's
     * unknown whether all of the requested keys exist at the time the function
     * is called), must have a function signature compatible with the one
     * expected by the users of the returned function.
     * 
     * @param array<mixed> $keys (Optional) An ordered list all keys for the
     *   returned callable to dispatch to, passing the return value of each
     *   dispatch as input to the next dispatch. Each key may be a callable to
     *   invoke to get the key to dispatch to for that element. Each real key
     *   may be null, in which case the first dispatchable default key will be
     *   dispatched to for that element. If not given or null, then all
     *   registered keys are dispatched to in the order they were registered.
     * @param mixed $defaultKey (Optional) The default key to dispatch to if any
     *   of the keys has not been registered in the dispatcher. This takes
     *   precedence over the dispatcher's global default key.
     * @param mixed $delegateKey (Optional) If given, the returned callable
     *   object will also pass any method calls directly to the dispatchable
     *   object associated with that key. The dispatch key does not need to be
     *   the same as the delegate key.
     * 
     * @return callable A callable that dispatches to each dispatchable key of
     *   those registered/requested (or the first dispatchable default key for
     *   any of the registered/requested keys that are not dispatchable), and
     *   that delegates method calls if a delegate key is given.
     * 
     * @throws UndispatchableError if the real key for the delegate key is not
     *   null, but is otherwise undispatchable.
     * 
     * @see toFirst()
     * @see DelegateFuncObj
     */
    public function funcToPipe(
            $keys = null,
            $defaultKey = null,
            $delegateKey = null
    ) {
        return new DelegateFuncObj(
            $this->getDelegateObject($delegateKey),
            function (...$args) use ($keys, $defaultKey) {
                return $this->toPipe($keys, $args, $defaultKey);
            }
        );
    }

    /* Static Helpers
    -------------------------------------------------- */

    /**
     * Create a new Dispatcher from a [key => callable] mapping, then return the
     * result of calling {@see funcToKey()} on the newly created Dispatcher.
     * 
     * This is a static helper to hide the mildly awkward PHP syntax for calling
     * a method immediately after creating an object, which is a common thing
     * to want to do with Dispatchers.
     * 
     * @param array<mixed,callable> $map The [key => callable] map to create a
     *   Dispatcher from.
     * @param mixed $key The key to pass to funcToKey().
     * @param mixed $defaultKey (Optional) The default key to pass to
     *  funcToKey().
     * @param mixed $delegateKey (Optional) The delegate key to pass to
     *  funcToKey().
     * 
     * @return callable The callable object returned by funcToKey().
     * 
     * @throws UndispatchableError if the real key for the delegate key is not
     *   null, but is otherwise undispatchable.
     */
    public static function funcToKeyOf(
            $map,
            $key,
            $defaultKey = null,
            $delegateKey = null
    ) {
        return (new self($map))->funcToKey($key, $defaultKey, $delegateKey);
    }

    /**
     * Create a new Dispatcher from a [key => callable] mapping, then return the
     * result of calling {@see funcToFirst()} on the newly created Dispatcher.
     * 
     * This is a static helper to hide the mildly awkward PHP syntax for calling
     * a method immediately after creating an object, which is a common thing
     * to want to do with Dispatchers.
     * 
     * @param array<mixed,callable> $map The [key => callable] map to create a
     *   Dispatcher from.
     * @param array<mixed> $keys The keys to pass to funcToFirst().
     * @param mixed $defaultKey (Optional) The default key to pass to
     *  funcToFirst().
     * @param mixed $delegateKey (Optional) The delegate key to pass to
     *  funcToFirst().
     * 
     * @throws UndispatchableError if the real key for the delegate key is not
     *   null, but is otherwise undispatchable.
     * 
     * @see funcToFirst()
     */
    public static function funcToFirstOf(
            $map,
            $keys = null,
            $defaultKey = null,
            $delegateKey = null
    ) {
        return (new self($map))->funcToFirst($keys, $defaultKey, $delegateKey);
    }

    /**
     * Create a new Dispatcher from a [key => callable] mapping, then return the
     * result of calling {@see funcToAll()} on the newly created Dispatcher.
     * 
     * This is a static helper to hide the mildly awkward PHP syntax for calling
     * a method immediately after creating an object, which is a common thing
     * to want to do with Dispatchers.
     * 
     * @param array<mixed,callable> $map The [key => callable] map to create a
     *   Dispatcher from.
     * @param array<mixed> $keys The keys to pass to funcToAll().
     * @param mixed $defaultKey (Optional) The default key to pass to
     *  funcToAll().
     * @param mixed $retKeys (Optional) The return keys to pass to funcToAll().
     * @param mixed $delegateKey (Optional) The delegate key to pass to
     *  funcToAll().
     * 
     * @throws UndispatchableError if the real key for the delegate key is not
     *   null, but is otherwise undispatchable.
     * 
     * @see funcToAll()
     */
    public static function funcToAllOf(
            $map,
            $keys = null,
            $defaultKey = null,
            $retKeys = null,
            $delegateKey = null
    ) {
        return (new self($map))->funcToAll(
            $keys, $defaultKey, $retKeys, $delegateKey
        );
    }

    /**
     * Create a new Dispatcher from a [key => callable] mapping, then return the
     * result of calling {@see funcToPipe()} on the newly created Dispatcher.
     * 
     * This is a static helper to hide the mildly awkward PHP syntax for calling
     * a method immediately after creating an object, which is a common thing
     * to want to do with Dispatchers.
     * 
     * @param array<mixed,callable> $map The [key => callable] map to create a
     *   Dispatcher from.
     * @param array<mixed> $keys The keys to pass to funcToPipe().
     * @param mixed $defaultKey (Optional) The default key to pass to
     *  funcToPipe().
     * @param mixed $delegateKey (Optional) The delegate key to pass to
     *  funcToPipe().
     * 
     * @throws UndispatchableError if the real key for the delegate key is not
     *   null, but is otherwise undispatchable.
     * 
     * @see funcToPipe()
     */
    public static function funcToPipeOf(
            $map,
            $keys = null,
            $defaultKey = null,
            $delegateKey = null
    ) {
        return (new self($map))->funcToPipe($keys, $defaultKey, $delegateKey);
    }

    /* Combinators over Dispatchers
    -------------------------------------------------- */

    /**
     * Returns a function that calls each function given in turn, returning
     * whatever is returned by the first call that does not throw an exception.
     * 
     * @param array<callable> The array of functions to check in turn.
     * @return mixed Whatever the first function not to throw returns.
     * @throws Exception If every function throws an exception.
     */
    public static function funcToFirstSuccessfulOf($fns) {
        return function (...$args) use ($fns) {
            foreach ($fns as $fn) {
                try {
                    return $fn(...$args);
                } catch (\Exception $e) { /* Do nothing */ }
            }
            throw new \Exception("No function in list was successful (didn't throw)");
        };
    }
}
