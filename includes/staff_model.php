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
            image_thumb VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            skills TEXT DEFAULT NULL,
            experience VARCHAR(150) DEFAULT NULL,
            years_experience INT UNSIGNED DEFAULT NULL,
            completed_projects INT UNSIGNED DEFAULT NULL,
            specialization VARCHAR(180) DEFAULT NULL,
            certifications TEXT DEFAULT NULL,
            email VARCHAR(150) DEFAULT NULL,
            phone VARCHAR(40) DEFAULT NULL,
            social_links TEXT DEFAULT NULL,
            status ENUM('active','inactive','on_leave') NOT NULL DEFAULT 'active',
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_staff_active_sort (active, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $columns = [
        'image' => "ALTER TABLE staff ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER role",
        'image_thumb' => "ALTER TABLE staff ADD COLUMN image_thumb VARCHAR(255) DEFAULT NULL AFTER image",
        'description' => "ALTER TABLE staff ADD COLUMN description TEXT DEFAULT NULL AFTER image_thumb",
        'skills' => "ALTER TABLE staff ADD COLUMN skills TEXT DEFAULT NULL AFTER description",
        'experience' => "ALTER TABLE staff ADD COLUMN experience VARCHAR(150) DEFAULT NULL AFTER skills",
        'years_experience' => "ALTER TABLE staff ADD COLUMN years_experience INT UNSIGNED DEFAULT NULL AFTER experience",
        'completed_projects' => "ALTER TABLE staff ADD COLUMN completed_projects INT UNSIGNED DEFAULT NULL AFTER years_experience",
        'specialization' => "ALTER TABLE staff ADD COLUMN specialization VARCHAR(180) DEFAULT NULL AFTER completed_projects",
        'certifications' => "ALTER TABLE staff ADD COLUMN certifications TEXT DEFAULT NULL AFTER specialization",
        'email' => "ALTER TABLE staff ADD COLUMN email VARCHAR(150) DEFAULT NULL AFTER certifications",
        'phone' => "ALTER TABLE staff ADD COLUMN phone VARCHAR(40) DEFAULT NULL AFTER email",
        'social_links' => "ALTER TABLE staff ADD COLUMN social_links TEXT DEFAULT NULL AFTER phone",
        'status' => "ALTER TABLE staff ADD COLUMN status ENUM('active','inactive','on_leave') NOT NULL DEFAULT 'active' AFTER social_links",
        'active' => "ALTER TABLE staff ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER status",
        'sort_order' => "ALTER TABLE staff ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER active",
        'created_at' => "ALTER TABLE staff ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER sort_order",
        'updated_at' => "ALTER TABLE staff ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ];
    foreach ($columns as $column => $sql) {
        if (!db_column_exists($pdo, 'staff', $column)) {
            $pdo->exec($sql);
        }
    }
    if (db_column_exists($pdo, 'staff', 'status') && db_column_exists($pdo, 'staff', 'active')) {
        $pdo->exec("UPDATE staff SET status = CASE WHEN active = 1 THEN 'active' ELSE 'inactive' END WHERE status IS NULL OR status = ''");
    }
}

/** @return list<array<string,mixed>> */
function vk_staff_get_all(PDO $pdo, bool $publicOnly = true): array
{
    vk_staff_ensure_table($pdo);
    $sql = 'SELECT * FROM staff';
    if ($publicOnly) {
        $sql .= " WHERE active = 1 AND status = 'active'";
    }
    $sql .= " ORDER BY
        CASE WHEN LOWER(role) LIKE '%owner%' OR LOWER(role) LIKE '%founder%' THEN 0 ELSE 1 END ASC,
        sort_order ASC,
        id DESC";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function vk_staff_get_by_id(PDO $pdo, int $id, bool $publicOnly = false): ?array
{
    vk_staff_ensure_table($pdo);
    $sql = 'SELECT * FROM staff WHERE id = ?';
    if ($publicOnly) {
        $sql .= " AND active = 1 AND status = 'active'";
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
            (name, role, image, image_thumb, description, skills, experience, years_experience, completed_projects, specialization, certifications, email, phone, social_links, status, active, sort_order)
         VALUES
            (:name, :role, :image, :image_thumb, :description, :skills, :experience, :years_experience, :completed_projects, :specialization, :certifications, :email, :phone, :social_links, :status, :active, :sort_order)'
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
            image_thumb = :image_thumb,
            description = :description,
            skills = :skills,
            experience = :experience,
            years_experience = :years_experience,
            completed_projects = :completed_projects,
            specialization = :specialization,
            certifications = :certifications,
            email = :email,
            phone = :phone,
            social_links = :social_links,
            status = :status,
            active = :active,
            sort_order = :sort_order
         WHERE id = :id'
    );
    $st->execute($payload);
}

function vk_staff_delete(PDO $pdo, int $id): void
{
    vk_staff_ensure_table($pdo);
    $row = vk_staff_get_by_id($pdo, $id, false);
    $st = $pdo->prepare('DELETE FROM staff WHERE id = ?');
    $st->execute([$id]);
    if ($row) {
        vk_staff_delete_upload_file((string) ($row['image'] ?? ''));
        vk_staff_delete_upload_file((string) ($row['image_thumb'] ?? ''));
    }
}

/** @param array<string,mixed> $data @return array<string,mixed> */
function vk_staff_db_payload(array $data): array
{
    $status = vk_staff_normalize_status((string) ($data['status'] ?? (!empty($data['active']) ? 'active' : 'inactive')));
    return [
        'name' => (string) $data['name'],
        'role' => (string) $data['role'],
        'image' => ($data['image'] ?? '') !== '' ? (string) $data['image'] : null,
        'image_thumb' => ($data['image_thumb'] ?? '') !== '' ? (string) $data['image_thumb'] : null,
        'description' => ($data['description'] ?? '') !== '' ? (string) $data['description'] : null,
        'skills' => ($data['skills'] ?? '') !== '' ? (string) $data['skills'] : null,
        'experience' => ($data['experience'] ?? '') !== '' ? (string) $data['experience'] : null,
        'years_experience' => ($data['years_experience'] ?? '') !== '' ? max(0, (int) $data['years_experience']) : null,
        'completed_projects' => ($data['completed_projects'] ?? '') !== '' ? max(0, (int) $data['completed_projects']) : null,
        'specialization' => ($data['specialization'] ?? '') !== '' ? (string) $data['specialization'] : null,
        'certifications' => ($data['certifications'] ?? '') !== '' ? (string) $data['certifications'] : null,
        'email' => ($data['email'] ?? '') !== '' ? (string) $data['email'] : null,
        'phone' => ($data['phone'] ?? '') !== '' ? (string) $data['phone'] : null,
        'social_links' => ($data['social_links'] ?? '') !== '' ? (string) $data['social_links'] : null,
        'status' => $status,
        'active' => $status === 'active' ? 1 : 0,
        'sort_order' => (int) ($data['sort_order'] ?? 0),
    ];
}

function vk_staff_normalize_status(string $status): string
{
    $status = strtolower(trim(str_replace('-', '_', $status)));
    return in_array($status, ['active', 'inactive', 'on_leave'], true) ? $status : 'inactive';
}

function vk_staff_status_label(string $status): string
{
    return match (vk_staff_normalize_status($status)) {
        'active' => 'Active',
        'on_leave' => 'On Leave',
        default => 'Inactive',
    };
}

function vk_staff_is_owner(array $row): bool
{
    $role = strtolower((string) ($row['role'] ?? ''));
    return str_contains($role, 'owner') || str_contains($role, 'founder');
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

/** @return list<string> */
function vk_staff_certifications_list(?string $certifications): array
{
    $certifications = trim((string) $certifications);
    if ($certifications === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', preg_split('/[,;\r\n]+/', $certifications) ?: [])));
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
    $path = vk_staff_image_path($image);
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    return public_asset_url($path !== '' ? $path : vk_staff_default_avatar_path());
}

function vk_staff_display_image(array $row, bool $thumb = false): string
{
    $image = $thumb && !empty($row['image_thumb']) ? (string) $row['image_thumb'] : (string) ($row['image'] ?? '');
    return vk_staff_image_url($image);
}

function vk_staff_default_avatar_path(): string
{
    return 'assets/images/default-avatar.svg';
}

function vk_staff_default_avatar_url(): string
{
    return public_asset_url(vk_staff_default_avatar_path());
}

function vk_staff_image_onerror_attr(): string
{
    return "this.onerror=null;this.src='" . e(vk_staff_default_avatar_url()) . "';";
}

function vk_staff_image_path(?string $image): string
{
    $image = trim(str_replace('\\', '/', (string) $image));
    if ($image === '') {
        return '';
    }

    if (preg_match('#^https?://([^/]+)(/.*)?$#i', $image, $m)) {
        $host = strtolower((string) ($m[1] ?? ''));
        $path = ltrim((string) ($m[2] ?? ''), '/');
        $isLocal = $host === 'localhost'
            || str_starts_with($host, 'localhost:')
            || $host === '127.0.0.1'
            || str_starts_with($host, '127.0.0.1:')
            || $host === '::1'
            || str_starts_with($host, '[::1]:');

        if (!$isLocal && $host !== strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))) {
            return $image;
        }

        $image = $path;
    }

    $image = vk_normalize_upload_relative_path($image);
    $image = preg_replace('#^(?:VK|vk)/#', '', $image) ?? $image;

    $markerPaths = ['uploads/staff/', 'assets/images/staff/'];
    foreach ($markerPaths as $marker) {
        $pos = stripos($image, $marker);
        if ($pos !== false) {
            $image = substr($image, $pos);
            break;
        }
    }

    if (!str_contains($image, '/')) {
        $basename = basename($image);
        foreach (['uploads/staff/' . $basename, 'assets/images/staff/' . $basename] as $candidate) {
            if (vk_staff_public_file_is_readable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    return vk_staff_public_file_is_readable($image) ? $image : '';
}

function vk_staff_public_file_is_readable(string $relativePath): bool
{
    $relativePath = vk_normalize_upload_relative_path($relativePath);
    if ($relativePath === '') {
        return false;
    }
    $full = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    return is_file($full) && is_readable($full);
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
    foreach (['years_experience' => 'Years of experience', 'completed_projects' => 'Completed projects'] as $key => $label) {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value !== '' && (!ctype_digit($value) || (int) $value > 9999)) {
            $errors[] = $label . ' must be a valid positive number.';
        }
    }

    return $errors;
}

/** @return array{image:?string,image_thumb:?string} */
function vk_staff_upload_image(string $field, ?string $existing = null, ?string $existingThumb = null): array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['image' => $existing, 'image_thumb' => $existingThumb];
    }
    $file = $_FILES[$field];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(vk_staff_upload_error_message($error));
    }
    if ((int) ($file['size'] ?? 0) <= 0) {
        throw new RuntimeException('Choose a non-empty profile image.');
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Profile image must be 5 MB or smaller.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Upload temporary file is missing. Please choose the image again.');
    }

    $originalName = (string) ($file['name'] ?? 'profile');
    $originalExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($originalExt, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new RuntimeException('Only JPG, JPEG, PNG, and WebP profile images are allowed.');
    }
    $info = @getimagesize($tmp);
    if (!is_array($info) || empty($info[0]) || empty($info[1])) {
        throw new RuntimeException('The uploaded file is not a readable image.');
    }
    $mime = vk_staff_detect_mime($tmp);
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
    if ($ext === '' || !vk_staff_mime_matches_extension($mime, $originalExt)) {
        throw new RuntimeException('Image type does not match the uploaded file extension.');
    }

    $dir = ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'staff';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create uploads/staff directory.');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('uploads/staff is not writable.');
    }

    $safeBase = vk_staff_safe_filename(pathinfo($originalName, PATHINFO_FILENAME));
    $name = 'staff-' . date('YmdHis') . '-' . $safeBase . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $thumbName = preg_replace('/\.' . preg_quote($ext, '/') . '$/', '-thumb.' . $ext, $name) ?: ('thumb-' . $name);
    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    $thumbDest = $dir . DIRECTORY_SEPARATOR . $thumbName;

    if (vk_staff_gd_available($mime)) {
        vk_staff_resize_image($tmp, $dest, $mime, 1280, 1280);
        vk_staff_resize_image($tmp, $thumbDest, $mime, 420, 420, true);
    } elseif (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    @chmod($dest, 0644);
    if (is_file($thumbDest)) {
        @chmod($thumbDest, 0644);
    }
    vk_staff_delete_upload_file($existing);
    vk_staff_delete_upload_file($existingThumb);

    return [
        'image' => 'uploads/staff/' . $name,
        'image_thumb' => is_file($thumbDest) ? 'uploads/staff/' . $thumbName : 'uploads/staff/' . $name,
    ];
}

function vk_staff_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Profile image is too large. Maximum size is 5 MB.',
        UPLOAD_ERR_PARTIAL => 'Image upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server upload temp directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file.',
        UPLOAD_ERR_EXTENSION => 'A server extension blocked the upload.',
        default => 'Image upload failed. Please try another JPG, PNG, or WebP file.',
    };
}

function vk_staff_detect_mime(string $tmp): string
{
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return (string) ($finfo->file($tmp) ?: '');
    }
    $info = @getimagesize($tmp);
    return is_array($info) ? (string) ($info['mime'] ?? '') : '';
}

function vk_staff_mime_matches_extension(string $mime, string $ext): bool
{
    return match ($mime) {
        'image/jpeg' => in_array($ext, ['jpg', 'jpeg'], true),
        'image/png' => $ext === 'png',
        'image/webp' => $ext === 'webp',
        default => false,
    };
}

function vk_staff_safe_filename(string $name): string
{
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?? 'profile';
    $name = trim($name, '-');
    return substr($name !== '' ? $name : 'profile', 0, 48);
}

function vk_staff_gd_available(string $mime): bool
{
    return extension_loaded('gd') && match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
        'image/png' => function_exists('imagecreatefrompng') && function_exists('imagepng'),
        'image/webp' => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
        default => false,
    };
}

function vk_staff_resize_image(string $srcPath, string $destPath, string $mime, int $maxW, int $maxH, bool $square = false): void
{
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($srcPath),
        'image/png' => @imagecreatefrompng($srcPath),
        'image/webp' => @imagecreatefromwebp($srcPath),
        default => false,
    };
    if (!$src) {
        throw new RuntimeException('Could not process uploaded image.');
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw < 1 || $sh < 1) {
        imagedestroy($src);
        throw new RuntimeException('Invalid image dimensions.');
    }

    if ($square) {
        $side = min($sw, $sh);
        $sx = (int) floor(($sw - $side) / 2);
        $sy = (int) floor(($sh - $side) / 2);
        $dw = $maxW;
        $dh = $maxH;
        $cropW = $side;
        $cropH = $side;
    } else {
        $ratio = min($maxW / $sw, $maxH / $sh, 1);
        $dw = max(1, (int) round($sw * $ratio));
        $dh = max(1, (int) round($sh * $ratio));
        $sx = 0;
        $sy = 0;
        $cropW = $sw;
        $cropH = $sh;
    }

    $dst = imagecreatetruecolor($dw, $dh);
    if (!$dst) {
        imagedestroy($src);
        throw new RuntimeException('Could not allocate image memory.');
    }
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $dw, $dh, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $dw, $dh, $cropW, $cropH);
    imagedestroy($src);

    $ok = match ($mime) {
        'image/jpeg' => @imagejpeg($dst, $destPath, 82),
        'image/png' => @imagepng($dst, $destPath, 7),
        'image/webp' => @imagewebp($dst, $destPath, 82),
        default => false,
    };
    imagedestroy($dst);
    if (!$ok) {
        throw new RuntimeException('Could not save optimized image.');
    }
}

function vk_staff_delete_upload_file(?string $relativePath): void
{
    $relativePath = vk_normalize_upload_relative_path((string) $relativePath);
    if ($relativePath === '' || !str_starts_with($relativePath, 'uploads/staff/')) {
        return;
    }
    $full = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $uploadsRoot = realpath(ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'staff');
    $target = realpath($full);
    if ($uploadsRoot && $target && str_starts_with($target, $uploadsRoot) && is_file($target)) {
        @unlink($target);
    }
}
