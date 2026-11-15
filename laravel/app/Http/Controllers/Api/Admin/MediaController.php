<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Finder\SplFileInfo;

class MediaController
{
    public function index(): JsonResponse
    {
        $root = public_path('uploads');
        if (! File::isDirectory($root)) {
            return response()->json([]);
        }

        $files = [];
        foreach (File::allFiles($root) as $file) {
            /** @var SplFileInfo $file */
            if (str_starts_with($file->getFilename(), '.')) {
                continue;
            }
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $files[] = [
                'url' => '/uploads/'.$relative,
                'path' => $relative,
                'size' => $file->getSize(),
                'createdAt' => gmdate('Y-m-d\TH:i:s.000\Z', $file->getMTime()),
            ];
        }

        usort($files, fn ($a, $b) => strcmp($b['createdAt'], $a['createdAt']));

        return response()->json($files);
    }

    public function store(Request $request): JsonResponse
    {
        $uploaded = $request->file('files');
        if (! $uploaded) {
            throw ValidationException::withMessages(['files' => 'Файлы не переданы']);
        }

        $files = is_array($uploaded) ? $uploaded : [$uploaded];
        if (count($files) > 20) {
            throw ValidationException::withMessages(['files' => 'Не больше 20 файлов за раз']);
        }

        foreach ($files as $file) {
            Validator::make(['file' => $file], [
                'file' => [
                    'required',
                    'file',
                    'max:10240',
                    'mimetypes:image/jpeg,image/png,image/webp,image/avif,image/gif,image/svg+xml,application/pdf',
                ],
            ], [
                'file.max' => 'Файл больше 10 МБ',
                'file.mimetypes' => 'Недопустимый тип файла',
            ])->validate();
        }

        $month = now()->format('Y-m');
        $directory = public_path('uploads/'.$month);
        File::ensureDirectoryExists($directory);

        $result = [];
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $size = $file->getSize();
            $mimetype = $file->getMimeType();
            $originalName = $file->getClientOriginalName();
            $name = Str::uuid()->toString().'.'.$extension;
            $file->move($directory, $name);
            $relative = $month.'/'.$name;

            $result[] = [
                'url' => '/uploads/'.$relative,
                'path' => $relative,
                'size' => $size,
                'mimetype' => $mimetype,
                'originalName' => $originalName,
            ];
        }

        return response()->json($result, 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $relative = str_replace('\\', '/', (string) $request->query('path', ''));
        if ($relative === '' || str_contains($relative, '..')) {
            throw ValidationException::withMessages(['path' => 'Некорректный путь']);
        }

        $root = realpath(public_path('uploads'));
        $full = realpath(public_path('uploads/'.$relative));

        if ($root === false || $full === false || ! str_starts_with(str_replace('\\', '/', $full), str_replace('\\', '/', $root).'/')) {
            throw ValidationException::withMessages(['path' => 'Некорректный путь']);
        }

        if (! File::isFile($full)) {
            abort(404);
        }

        File::delete($full);

        return response()->json(null, 204);
    }
}
