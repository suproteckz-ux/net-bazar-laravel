<?php

namespace App\Http\Controllers;

use App\Models\HomeTile;

class HomeController extends Controller
{
    public function index()
    {
        $tiles = HomeTile::with(['manualCategory', 'kaspiCategory'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('home', [
            'tiles'     => $tiles,
            'benefits'  => config('netbazar.benefits'),
            'heroImage' => '/images/home/hero.png',
        ]);
    }
}
