<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfferSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereIn('email', [
            'youssef@qribly.test',
            'sara@qribly.test',
            'omar@qribly.test',
            'salma@qribly.test',
            'test@qribly.test',
        ])->get()->keyBy('email');

        $categories = Category::all()->keyBy('name');

        $offers = [
            [
                'user' => 'youssef@qribly.test',
                'category' => 'Restaurants',
                'title' => 'Traditional Moroccan Restaurant',
                'description' => 'Authentic Moroccan dishes including tajine, couscous and pastilla.',
                'type' => 'service',
                'price' => 120,
                'is_negotiable' => false,
                'status' => 'active',
                'latitude' => 31.6340,
                'longitude' => -8.0080,
            ],
            [
                'user' => 'youssef@qribly.test',
                'category' => 'Electronics',
                'title' => 'Used Laptop',
                'description' => 'Good condition laptop suitable for studying and everyday work.',
                'type' => 'product',
                'price' => 3500,
                'is_negotiable' => true,
                'status' => 'active',
                'latitude' => 31.6295,
                'longitude' => -7.9890,
            ],
            [
                'user' => 'sara@qribly.test',
                'category' => 'Clothing',
                'title' => 'Traditional Moroccan Caftan',
                'description' => 'Elegant Moroccan caftan suitable for weddings and special occasions.',
                'type' => 'product',
                'price' => 850,
                'is_negotiable' => true,
                'status' => 'active',
                'latitude' => 31.6250,
                'longitude' => -7.9895,
            ],
            [
                'user' => 'sara@qribly.test',
                'category' => 'Beauty',
                'title' => 'Hair Styling Service',
                'description' => 'Professional hair styling service for events and special occasions.',
                'type' => 'service',
                'price' => 150,
                'is_negotiable' => false,
                'status' => 'active',
                'latitude' => 31.6410,
                'longitude' => -8.0170,
            ],
            [
                'user' => 'omar@qribly.test',
                'category' => 'Transportation',
                'title' => 'Local Delivery Service',
                'description' => 'Fast local delivery service for small packages around Marrakech.',
                'type' => 'service',
                'price' => 30,
                'is_negotiable' => true,
                'status' => 'active',
                'latitude' => 31.6205,
                'longitude' => -8.0120,
            ],
            [
                'user' => 'omar@qribly.test',
                'category' => 'Electronics',
                'title' => 'Smartphone',
                'description' => 'Used smartphone in good working condition with charger included.',
                'type' => 'product',
                'price' => 1800,
                'is_negotiable' => true,
                'status' => 'reserved',
                'latitude' => 31.6365,
                'longitude' => -7.9970,
            ],
            [
                'user' => 'salma@qribly.test',
                'category' => 'Education',
                'title' => 'English Tutoring',
                'description' => 'Private English lessons for students and beginners.',
                'type' => 'service',
                'price' => 100,
                'is_negotiable' => true,
                'status' => 'active',
                'latitude' => 31.6480,
                'longitude' => -8.0020,
            ],
            [
                'user' => 'salma@qribly.test',
                'category' => 'Groceries',
                'title' => 'Fresh Local Produce',
                'description' => 'Fresh vegetables and fruits sourced from local producers.',
                'type' => 'product',
                'price' => 75,
                'is_negotiable' => false,
                'status' => 'active',
                'latitude' => 31.6150,
                'longitude' => -7.9980,
            ],
            [
                'user' => 'test@qribly.test',
                'category' => 'Home Services',
                'title' => 'Home Cleaning Service',
                'description' => 'Reliable home cleaning service for apartments and houses.',
                'type' => 'service',
                'price' => 200,
                'is_negotiable' => true,
                'status' => 'active',
                'latitude' => 31.6430,
                'longitude' => -7.9780,
            ],
            [
                'user' => 'test@qribly.test',
                'category' => 'Restaurants',
                'title' => 'Homemade Moroccan Meals',
                'description' => 'Homemade Moroccan meals prepared fresh and available for local delivery.',
                'type' => 'product',
                'price' => 90,
                'is_negotiable' => false,
                'status' => 'active',
                'latitude' => 31.6280,
                'longitude' => -8.0250,
            ],
            // Offres à distances croissantes du centre de Marrakech, pour tester
            // le filtre de rayon de "Près de moi" (GET /offers/nearby) sur plusieurs
            // paliers plutôt qu'un seul cluster serré à moins de 3 km.
            [
                'user' => 'salma@qribly.test',
                'category' => 'Home Services',
                'title' => 'Palmeraie Villa Cleaning Service',
                'description' => 'Cleaning service for villas and holiday homes in the Palmeraie area.',
                'type' => 'service',
                'price' => 180,
                'is_negotiable' => true,
                'status' => 'active',
                'latitude' => 31.7000, // ~8 km du centre
                'longitude' => -8.0300,
            ],
            [
                'user' => 'test@qribly.test',
                'category' => 'Groceries',
                'title' => 'Farm Fresh Vegetables Delivery',
                'description' => 'Weekly delivery of fresh vegetables from farms north of Marrakech.',
                'type' => 'product',
                'price' => 60,
                'is_negotiable' => false,
                'status' => 'active',
                'latitude' => 31.8000, // ~21 km du centre
                'longitude' => -7.9000,
            ],
            [
                'user' => 'omar@qribly.test',
                'category' => 'Transportation',
                'title' => 'Mountain Bike Rental',
                'description' => 'Mountain bike rental for trails near the Ourika valley.',
                'type' => 'service',
                'price' => 80,
                'is_negotiable' => true,
                'status' => 'active',
                'latitude' => 31.3500, // ~31 km du centre
                'longitude' => -7.9500,
            ],
            [
                'user' => 'sara@qribly.test',
                'category' => 'Home Services',
                'title' => 'Essaouira Beachfront Apartment Cleaning',
                'description' => 'Cleaning service for beachfront apartments in Essaouira.',
                'type' => 'service',
                'price' => 150,
                'is_negotiable' => false,
                'status' => 'active',
                'latitude' => 31.5085, // ~167 km du centre, hors de tous les rayons proposés
                'longitude' => -9.7595,
            ],
            [
                'user' => 'youssef@qribly.test',
                'category' => 'Electronics',
                'title' => 'Casablanca Electronics Repair Shop',
                'description' => 'Repair service for smartphones, laptops and small appliances.',
                'type' => 'service',
                'price' => 90,
                'is_negotiable' => true,
                'status' => 'active',
                'latitude' => 33.5731, // ~230 km du centre, hors de tous les rayons proposés
                'longitude' => -7.5898,
            ],
        ];

        foreach ($offers as $data) {
            $user = $users[$data['user']];
            $category = $categories[$data['category']];

            $location = DB::selectOne(
                'SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography AS location',
                [$data['longitude'], $data['latitude']]
            )->location;

            Offer::updateOrCreate(
                [
                    'title' => $data['title'],
                ],
                [
                    'user_id' => $user->id,
                    'category_id' => $category->id,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'type' => $data['type'],
                    'price' => $data['price'],
                    'is_negotiable' => $data['is_negotiable'],
                    'status' => $data['status'],
                    'location' => $location,
                ]
            );
        }
    }
}