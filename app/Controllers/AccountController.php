<?php
namespace App\Controllers;
final class AccountController extends BaseController { public function orders(): void{$this->render('account/orders');} public function bookings(): void{$this->render('account/bookings');} }
