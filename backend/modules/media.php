<?php
declare(strict_types=1);

namespace App;

const MEDIA_TYPES = [
    'image/jpeg' => '.jpg',
    'image/png' => '.png',
    'image/webp' => '.webp',
    'image/avif' => '.avif',
    'image/gif' => '.gif',
    'image/svg+xml' => '.svg',
    'application/pdf' => '.pdf', // меню в PDF, прайс
];

/** Папка загрузок: UPLOAD_DIR относительно backend/, по умолчанию ../uploads. */
function uploadRoot(): string
{
    $dir = env('UPLOAD_DIR');
    $path = preg_match('#^([a-zA-Z]:)?[\\\\/]#', $dir) ? $dir : BACKEND_DIR . '/' . $dir;
    if (!is_dir($path)) @mkdir($path, 0775, true);
    return rtrim(str_replace('\\', '/', realpath($path) ?: $path), '/');
}

function mediaUrl(string $rel): string
{
    return rtrim(env('PUBLIC_URL'), '/') . '/uploads/' . $rel;
}

function detectMime(string $file, string $clientType): string
{
    if (function_exists('finfo_open')) {
        $mime = (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file);
        // finfo распознаёт SVG как text/xml или image/svg
        if (in_array($mime, ['text/xml', 'application/xml', 'image/svg', 'text/plain'], true) && $clientType === 'image/svg+xml') return $clientType;
        return $mime;
    }
    return $clientType;
}

function uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
    $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/** $_FILES['files'] (поле files[] или files) → список файлов. */
function uploadedFiles(): array
{
    $f = $_FILES['files'] ?? null;
    if (!$f) return [];
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach (array_keys($f['name']) as $i) {
        $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

return [
    'admin' => function (Router $r) {
        /** multipart/form-data, поле `files` (до 20 файлов). */
        $r->post('/media', function () {
            $files = uploadedFiles();
            if (!$files) throw HttpError::badRequest('Файлы не переданы (поле files)');
            if (count($files) > 20) throw HttpError::badRequest('Ошибка загрузки: не больше 20 файлов за раз');

            $maxBytes = env('UPLOAD_MAX_MB') * 1024 * 1024;
            foreach ($files as &$file) {
                if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > $maxBytes) {
                    throw HttpError::badRequest('Ошибка загрузки: файл больше ' . env('UPLOAD_MAX_MB') . ' МБ');
                }
                if ($file['error'] !== UPLOAD_ERR_OK) throw HttpError::badRequest("Ошибка загрузки: код {$file['error']}");
                $file['mime'] = detectMime($file['tmp_name'], (string) $file['type']);
                if (!isset(MEDIA_TYPES[$file['mime']])) throw HttpError::badRequest("Недопустимый тип файла: {$file['mime']}");
            }
            unset($file);

            // Раскладываем по месяцам, чтобы не держать тысячи файлов в одной папке
            $month = gmdate('Y-m');
            $dir = uploadRoot() . "/$month";
            if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new \RuntimeException("Не удалось создать папку $dir");

            $result = [];
            foreach ($files as $file) {
                $rel = $month . '/' . uuid() . MEDIA_TYPES[$file['mime']];
                $dest = uploadRoot() . "/$rel";
                $moved = is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $dest) : rename($file['tmp_name'], $dest);
                if (!$moved) throw new \RuntimeException("Не удалось сохранить файл в $dest");
                @chmod($dest, 0644);
                $result[] = ['url' => mediaUrl($rel), 'path' => $rel, 'size' => $file['size'], 'mimetype' => $file['mime'], 'originalName' => $file['name']];
            }
            return Response::created($result);
        });

        $r->get('/media', function () {
            $root = uploadRoot();
            $files = [];
            if (is_dir($root)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    /** @var \SplFileInfo $file */
                    if (!$file->isFile() || str_starts_with($file->getFilename(), '.')) continue;
                    $rel = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($root)), '/');
                    $files[] = ['url' => mediaUrl($rel), 'path' => $rel, 'size' => $file->getSize(), 'createdAt' => gmdate('Y-m-d\TH:i:s.000\Z', $file->getMTime())];
                }
            }
            usort($files, fn ($a, $b) => strcmp($b['createdAt'], $a['createdAt']));
            return $files;
        });

        /** DELETE /api/admin/media?path=2026-10/uuid.jpg */
        $r->delete('/media', function (Request $req) {
            $rel = (string) ($req->query['path'] ?? '');
            $root = uploadRoot();
            $full = realpath("$root/$rel");
            // Защита от path traversal: файл должен лежать внутри папки загрузок
            if ($rel === '' || ($full !== false && !str_starts_with(str_replace('\\', '/', $full), "$root/"))) {
                throw HttpError::badRequest('Некорректный путь');
            }
            if ($full === false || !is_file($full) || !@unlink($full)) throw HttpError::notFound('Файл не найден');
            return Response::noContent();
        });
    },
];
