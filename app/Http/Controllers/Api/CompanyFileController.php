<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Brand\CompanyFileAuthorizer;
use App\Support\Brand\CompanyFilePolicy;
use App\Support\Brand\CompanyFileService;
use Illuminate\Http\Request;

class CompanyFileController extends Controller
{
    public function __construct(private CompanyFileService $files)
    {
    }

    /**
     * Authenticated + signed stream for company / sensitive files.
     *
     * Query (all required for private files):
     *   disk, path, resource, resource_id
     *
     * Signature proves the server issued the URL; JWT + record ACL prove
     * the current user may still view the owning resource. A valid signature
     * alone is never treated as permission.
     */
    public function serve(Request $request)
    {
        $disk = (string) $request->query('disk', CompanyFileService::DISK_COMPANY);
        $path = CompanyFilePolicy::normalize((string) $request->query('path', ''));
        $resource = (string) $request->query('resource', '');
        $resourceId = (int) $request->query('resource_id', 0);

        if ($path === '' || $resource === '' || $resourceId < 1) {
            abort(404);
        }

        if (! in_array($resource, CompanyFileAuthorizer::KNOWN_TYPES, true)) {
            abort(404);
        }

        return $this->files->streamAuthorized($disk, $path, $resource, $resourceId);
    }
}
