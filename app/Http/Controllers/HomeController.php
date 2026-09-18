<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'tiles'     => config('netbazar.home_tiles'),
            'benefits'  => config('netbazar.benefits'),
            'heroImage' => '/images/home/hero.png',
        ]);
    }
}
