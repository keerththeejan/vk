<?php
declare(strict_types=1);

function vk_staff_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            role VARCHAR(80) NOT NULL,
            image VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            skills TEXT DEFAULT NULL,
            experience VARCHAR(150) DEFAULT NULL,
            email VARCHAR(150) DEFAULT NULL,
            phone VARCHAR(40) DEFAULT NULL,
            social_links TEXT DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_staff_active_sort (active, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/** @return list<array<string,mixed>> */
function vk_staff_get_all(PDO $pdo, bool $publicOnly = true): array
{
    vk_staff_ensure_table($pdo);
    $sql = 'SELECT * FROM staff';
    if ($publicOnly) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id DESC';

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function vk_staff_get_by_id(PDO $pdo, int $id, bool $publicOnly = false): ?array
{
    vk_staff_ensure_table($pdo);
    $sql = 'SELECT * FROM staff WHERE id = ?';
    if ($publicOnly) {
        $sql .= ' AND active = 1';
    }
    $sql .= ' LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @param array<string,mixed> $data */
function vk_staff_insert(PDO $pdo, array $data): int
{
    vk_staff_ensure_table($pdo);
    $st = $pdo->prepare(
        'INSERT INTO staff
            (name, role, image, description, skills, experience, email, phone, social_links, active, sort_order)
         VALUES
            (:name, :role, :image, :description, :skills, :experience, :email, :phone, :social_links, :active, :sort_order)'
    );
    $st->execute(vk_staff_db_payload($data));

    return (int) $pdo->lastInsertId();
}

/** @param array<string,mixed> $data */
function vk_staff_update(PDO $pdo, int $id, array $data): void
{
    vk_staff_ensure_table($pdo);
    $payload = vk_staff_db_payload($data);
    $payload['id'] = $id;
    $st = $pdo->prepare(
        'UPDATE staff SET
            name = :name,
            role = :role,
            image = :image,
            description = :description,
            skills = :skills,
            experience = :experience,
            email = :email,
            phone = :phone,
            social_links = :social_links,
            active = :active,
            sort_order = :sort_order
         WHERE id = :id'
    );
    $st->execute($payload);
}

function vk_staff_delete(PDO $pdo, int $id): void
{
    vk_staff_ensure_table($pdo);
    $st = $pdo->prepare('DELETE FROM staff WHERE id = ?');
    $st->execute([$id]);
}

/** @param array<string,mixed> $data @return array<string,mixed> */
function vk_staff_db_payload(array $data): array
{
    return [
        'name' => (string) $data['name'],
        'role' => (string) $data['role'],
        'image' => ($data['image'] ?? '') !== '' ? (string) $data['image'] : null,
        'description' => ($data['description'] ?? '') !== '' ? (string) $data['description'] : null,
        'skills' => ($data['skills'] ?? '') !== '' ? (string) $data['skills'] : null,
        'experience' => ($data['experience'] ?? '') !== '' ? (string) $data['experience'] : null,
        'email' => ($data['email'] ?? '') !== '' ? (string) $data['email'] : null,
        'phone' => ($data['phone'] ?? '') !== '' ? (string) $data['phone'] : null,
        'social_links' => ($data['social_links'] ?? '') !== '' ? (string) $data['social_links'] : null,
        'active' => !empty($data['active']) ? 1 : 0,
        'sort_order' => (int) ($data['sort_order'] ?? 0),
    ];
}

/** @return list<string> */
function vk_staff_skills_list(?string $skills): array
{
    $skills = trim((string) $skills);
    if ($skills === '') {
        return [];
    }
    $decoded = json_decode($skills, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map(static fn($v): string => trim((string) $v), $decoded)));
    }

    return array_values(array_filter(array_map('trim', preg_split('/[,;\r\n]+/', $skills) ?: [])));
}

/** @return list<array{label:string,url:string}> */
function vk_staff_social_links(?string $raw): array
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return [];
    }
    $out = [];
    foreach (preg_split('/\r\n|\n|\r/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $label = 'Link';
        $url = $line;
        if (str_contains($line, '|')) {
            [$label, $url] = array_map('trim', explode('|', $line, 2));
        }
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $out[] = ['label' => $label !== '' ? $label : 'Link', 'url' => $url];
        }
    }

    return $out;
}

function vk_staff_image_url(?string $image): string
{
    $image = trim((string) $image);
    if ($image === '') {
        return '';
    }
    if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
        return $image;
    }

    return base_url($image);
}

function vk_staff_validate(array $input): array
{
    $errors = [];
    $name = trim((string) ($input['name'] ?? ''));
    $role = trim((string) ($input['role'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (strlen($name) > 150) {
        $errors[] = 'Name must be 150 characters or fewer.';
    }
    if ($role === '') {
        $errors[] = 'Role is required.';
    } elseif (strlen($role) > 80) {
        $errors[] = 'Role must be 80 characters or fewer.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{6,40}$/', $phone)) {
        $errors[] = 'Enter a valid phone number.';
    }

    return $errors;
}

function vk_staff_upload_image(string $field, ?string $existing = null): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Try again with a smaller JPG or PNG file.');
    }
    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Profile image must be 2 MB or smaller.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $info = @getimagesize($tmp);
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        default => '',
    };
    if ($ext === '') {
        throw new RuntimeException('Only JPG and PNG profile images are allowed.');
    }

    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'staff';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create uploads/staff directory.');
    }

    $name = 'staff-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    return 'uploads/staff/' . $name;
}
