<?php

test('home page renders', function () {
    $this->get('/')->assertOk();
});

test('seller routes redirect guests to login', function () {
    $this->get('/seller')->assertRedirect();
});

test('admin routes are blocked for guests', function () {
    $this->get('/admin')->assertRedirect();
});
