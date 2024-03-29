<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $accounts = factory(App\Models\Account::class, 5)->create();

        \App\Models\Account::all()->each(function (App\Models\Account $account) {
            $listings = factory(App\Models\Listing::class, 2)->make();
            $account->listings()->saveMany($listings);
        });


        /*
        // $this->call(UsersTableSeeder::class);
		$this->call(AccountsTableSeeder::class);
		$this->call(ListingsTableSeeder::class);
         */
        /*
        $users = factory(App\User::class, 5)->create();
        $admin = factory(App\User::class, 1)->state('admin')->create();

        factory(App\Author::class, 15)->create()->each(function (App\Author $author) {
            factory(App\Book::class, 3)->create()->each(function (App\Book $book) use ($author) {
                $book->authors()->saveMany([
                    $author,
                ]);
            });
        });

        \App\Book::all()->each(function (App\Book $book) use ($users) {
            $reviews = factory(App\BookReview::class, 4)->make();
            $reviews->each(function (\App\BookReview $review) use ($users) {
                $review->user()->associate($users->random());
            });
            $book->reviews()->saveMany($reviews);
        });
         */



    }
}
