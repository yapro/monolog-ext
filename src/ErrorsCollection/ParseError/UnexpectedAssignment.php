<?php
class MyClass {
    const TRICK = 'old';
    public function __construct() {
        static::TRICK = 'new';// PHP Parse error:  syntax error, unexpected '=' in
    }
}
new MyClass();