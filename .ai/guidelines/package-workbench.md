# Package Workbench

- This is a Laravel package repository with Orchestra Testbench and no root `artisan` executable. Run Artisan commands through `php vendor/bin/testbench`.
- When running Laravel Boost commands directly, use `APP_BASE_PATH=. APP_ENV=local VIEW_COMPILED_PATH=bootstrap/cache php vendor/bin/testbench boost:<command>` so Boost scans this package instead of Testbench's internal application skeleton.
