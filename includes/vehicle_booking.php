<?php
declare(strict_types=1);

function vk_vehicle_auto_migrate(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS vehicle_customers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(160) NOT NULL,
            phone VARCHAR(64) NOT NULL,
            email VARCHAR(190) NOT NULL,
            address VARCHAR(255) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_vehicle_customers_email (email),
            INDEX idx_vehicle_customers_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    if (!db_column_exists($pdo, 'vehicle_customers', 'address')) {
        $pdo->exec('ALTER TABLE vehicle_customers ADD COLUMN address VARCHAR(255) DEFAULT NULL AFTER email');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS vehicle_drivers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            phone VARCHAR(64) NOT NULL,
            license_number VARCHAR(100) NOT NULL,
            availability ENUM('available','on_trip','off_duty') NOT NULL DEFAULT 'available',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_vehicle_drivers_license (license_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS vehicles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            vehicle_name VARCHAR(190) NOT NULL,
            vehicle_type ENUM('car','van','bike','lorry','bus') NOT NULL DEFAULT 'car',
            registration_number VARCHAR(64) NOT NULL,
            price_per_day DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            price_per_km DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            default_driver_charge DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            assigned_driver_id INT UNSIGNED DEFAULT NULL,
            status ENUM('available','booked','maintenance') NOT NULL DEFAULT 'available',
            image_path VARCHAR(512) DEFAULT NULL,
            seats INT UNSIGNED NOT NULL DEFAULT 4,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_vehicles_reg (registration_number),
            INDEX idx_vehicles_type_status (vehicle_type, status),
            CONSTRAINT fk_vehicles_driver FOREIGN KEY (assigned_driver_id) REFERENCES vehicle_drivers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS vehicle_bookings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_ref VARCHAR(40) NOT NULL,
            customer_id INT UNSIGNED NOT NULL,
            booking_type ENUM('rental','hire') NOT NULL,
            vehicle_id INT UNSIGNED DEFAULT NULL,
            driver_id INT UNSIGNED DEFAULT NULL,
            status ENUM('pending','confirmed','ongoing','completed','cancelled') NOT NULL DEFAULT 'pending',
            pickup_location VARCHAR(255) NOT NULL,
            pickup_lat DECIMAL(10,7) DEFAULT NULL,
            pickup_lng DECIMAL(10,7) DEFAULT NULL,
            drop_location VARCHAR(255) DEFAULT NULL,
            drop_lat DECIMAL(10,7) DEFAULT NULL,
            drop_lng DECIMAL(10,7) DEFAULT NULL,
            pickup_at DATETIME NOT NULL,
            return_at DATETIME DEFAULT NULL,
            vehicle_type ENUM('car','van','bike','lorry','bus') NOT NULL DEFAULT 'car',
            passengers INT UNSIGNED NOT NULL DEFAULT 1,
            distance_km DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            rental_days INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            driver_charge DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            special_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_vehicle_bookings_ref (booking_ref),
            INDEX idx_vehicle_bookings_customer (customer_id, created_at),
            INDEX idx_vehicle_bookings_status (status),
            INDEX idx_vehicle_bookings_type (booking_type),
            CONSTRAINT fk_vehicle_bookings_customer FOREIGN KEY (customer_id) REFERENCES vehicle_customers(id) ON DELETE CASCADE,
            CONSTRAINT fk_vehicle_bookings_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
            CONSTRAINT fk_vehicle_bookings_driver FOREIGN KEY (driver_id) REFERENCES vehicle_drivers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    vk_vehicle_seed_sample_data($pdo);
}

function vk_vehicle_customer(): ?array
{
    $id = (int) ($_SESSION['vk_vehicle_customer_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $pdo = db();
    vk_vehicle_auto_migrate($pdo);
    $st = $pdo->prepare('SELECT id, full_name, phone, email, address FROM vehicle_customers WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function vk_vehicle_require_customer(): array
{
    $u = vk_vehicle_customer();
    if (!$u) {
        flash_set('warning', 'Please login to continue.');
        redirect('/vehicle/login.php');
    }
    return $u;
}

function vk_vehicle_booking_ref(): string
{
    return 'VB-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function vk_vehicle_days(string $startAt, ?string $endAt): int
{
    $s = strtotime($startAt);
    $e = $endAt ? strtotime($endAt) : false;
    if ($s === false || $e === false || $e <= $s) {
        return 1;
    }
    return max(1, (int) ceil(($e - $s) / 86400));
}

function vk_vehicle_generate_password(int $length = 10): string
{
    $length = max(8, min(12, $length));
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $special = '@#$%&*!?';
    $all = $upper . $lower . $digits . $special;

    $chars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];
    while (count($chars) < $length) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }
    shuffle($chars);
    return implode('', $chars);
}

function vk_vehicle_login_url(): string
{
    return base_url('vehicle/login.php');
}

/**
 * @return array{ok:bool,error:?string}
 */
function vk_vehicle_send_credentials_email(PDO $pdo, string $toEmail, string $name, string $plainPassword): array
{
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email address'];
    }
    $body = "Hello {$name},\n\n"
        . "Your account has been successfully created.\n\n"
        . "Login Details:\n"
        . "Email: {$toEmail}\n"
        . "Password: {$plainPassword}\n\n"
        . "Login here:\n"
        . vk_vehicle_login_url() . "\n\n"
        . "Please change your password after first login.\n\n"
        . "Thank you,\nVK Transport Service";
    return vk_mailer_send($pdo, $toEmail, 'Your VK Vehicle Booking Account Details', $body, $name, [
        'template_type' => 'vehicle_registration',
    ]);
}

/**
 * @return array{ok:bool,error:?string}
 */
function vk_vehicle_send_credentials_whatsapp(string $phoneRaw, string $name, string $email, string $plainPassword): array
{
    if (!function_exists('vk_whatsapp_normalize_phone') || !function_exists('vk_whatsapp_bridge_send')) {
        return ['ok' => false, 'error' => 'WhatsApp bridge unavailable'];
    }
    $norm = vk_whatsapp_normalize_phone($phoneRaw);
    if ($norm === null) {
        return ['ok' => false, 'error' => 'Invalid phone for WhatsApp'];
    }
    $msg = "Hello {$name},\n\n"
        . "Your VK Vehicle Booking account is ready.\n\n"
        . "Login Details:\n"
        . "Email: {$email}\n"
        . "Password: {$plainPassword}\n\n"
        . "Login:\n"
        . vk_vehicle_login_url() . "\n\n"
        . "Please change your password after login.";

    $ok = vk_whatsapp_bridge_send($norm, $msg);
    return ['ok' => $ok, 'error' => $ok ? null : 'WhatsApp send failed'];
}

function vk_vehicle_seed_sample_data(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $drivers = [
            ['K. Suresh', '0777123456', 'B4512268', 'available', 1],
            ['T. Nimalan', '0777456789', 'B4528931', 'available', 1],
            ['R. Arul', '0777987654', 'B4581022', 'off_duty', 1],
        ];
        $stDriver = $pdo->prepare('INSERT INTO vehicle_drivers (name, phone, license_number, availability, active) VALUES (?,?,?,?,?)');
        foreach ($drivers as $d) {
            $stDriver->execute($d);
        }

        $vehicles = [
            ['Toyota Prius', 'car', 'CAA-4389', 12500, 180, 2500, 1, 'available', 'assets/images/services/automobile.svg', 4],
            ['Nissan Caravan', 'van', 'NCB-7721', 18500, 260, 3500, 2, 'available', 'assets/images/services/maintenance.svg', 12],
            ['Bajaj CT100', 'bike', 'BKE-1902', 3500, 65, 0, null, 'available', 'assets/images/services/default.svg', 2],
            ['Isuzu Lorry', 'lorry', 'LRY-5104', 26500, 420, 5500, 3, 'maintenance', 'assets/images/services/electrical.svg', 3],
        ];
        $stVehicle = $pdo->prepare(
            'INSERT INTO vehicles
            (vehicle_name, vehicle_type, registration_number, price_per_day, price_per_km, default_driver_charge, assigned_driver_id, status, image_path, seats)
            VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        foreach ($vehicles as $v) {
            $stVehicle->execute($v);
        }

        $custId = 0;
        $email = 'demo@vkvehicle.local';
        $stCustomer = $pdo->prepare('SELECT id FROM vehicle_customers WHERE email = ? LIMIT 1');
        $stCustomer->execute([$email]);
        $existing = $stCustomer->fetchColumn();
        if ($existing) {
            $custId = (int) $existing;
        } else {
            $hash = password_hash('demo1234', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO vehicle_customers (full_name, phone, email, address, password_hash) VALUES (?,?,?,?,?)')
                ->execute(['Demo Customer', '0777001122', $email, 'Kilinochchi', $hash]);
            $custId = (int) $pdo->lastInsertId();
        }

        $stBooking = $pdo->prepare(
            'INSERT INTO vehicle_bookings
            (booking_ref, customer_id, booking_type, vehicle_id, driver_id, status, pickup_location, pickup_lat, pickup_lng, drop_location, drop_lat, drop_lng, pickup_at, return_at, vehicle_type, passengers, distance_km, rental_days, unit_price, driver_charge, total_amount, special_notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        $pickup1 = date('Y-m-d H:i:s', strtotime('+1 day 09:00'));
        $return1 = date('Y-m-d H:i:s', strtotime('+3 day 09:00'));
        $stBooking->execute([
            'VB-SAMPLE-001',
            $custId,
            'rental',
            1,
            null,
            'confirmed',
            'Kilinochchi Bus Stand',
            9.3961,
            80.3982,
            null,
            null,
            null,
            $pickup1,
            $return1,
            'car',
            4,
            0,
            2,
            12500,
            0,
            25000,
            'Sample rental booking',
        ]);

        $pickup2 = date('Y-m-d H:i:s', strtotime('+2 day 08:30'));
        $return2 = date('Y-m-d H:i:s', strtotime('+2 day 18:30'));
        $stBooking->execute([
            'VB-SAMPLE-002',
            $custId,
            'hire',
            2,
            2,
            'pending',
            'Jaffna Town',
            9.6615,
            80.0255,
            'Kilinochchi Central',
            9.3803,
            80.3761,
            $pickup2,
            $return2,
            'van',
            6,
            62.5,
            1,
            260,
            3500,
            19750,
            'Sample hire booking with driver',
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
