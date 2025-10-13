<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $testimonials
        ]);
    }

    public function show($id)
    {
        $testimonial = Testimonial::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $testimonial
        ]);
    }
}
