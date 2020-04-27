# Installation

Create a new `.env` file in the root folder and copy content of `.env.example` into .env. Update 

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=laravel
    DB_USERNAME=root
    DB_PASSWORD=

to your database parameters.

Next, run `composer install` in terminal in the root folder of the application then `php artisan migrate` to create tables in the database.

Finally, run `php artisan serve` to fire up the inbuilt server to test.
