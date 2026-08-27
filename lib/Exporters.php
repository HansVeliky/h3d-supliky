<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Exporters
{
    /**
     * Binary STL. Every box is written as its own shell inside a single file,
     * so Split to Parts still separates them in the slicer.
     */
    public static function binarySTL(array $meshes, string $header = 'Honza3D Drawer Organizer'): string
    {
        $total = 0;
        foreach ($meshes as $m) {
            $total += count($m['tris']);
        }

        $out = str_pad(substr($header, 0, 79), 80, ' ');
        $out .= pack('V', $total);

        foreach ($meshes as $mesh) {
            $v = $mesh['vertices'];
            foreach ($mesh['tris'] as [$ia, $ib, $ic]) {
                $a = $v[$ia];
                $b = $v[$ib];
                $c = $v[$ic];

                $ux = $b[0] - $a[0]; $uy = $b[1] - $a[1]; $uz = $b[2] - $a[2];
                $vx = $c[0] - $a[0]; $vy = $c[1] - $a[1]; $vz = $c[2] - $a[2];

                $nx = $uy * $vz - $uz * $vy;
                $ny = $uz * $vx - $ux * $vz;
                $nz = $ux * $vy - $uy * $vx;

                $len = sqrt($nx * $nx + $ny * $ny + $nz * $nz);
                if ($len > 0) {
                    $nx /= $len; $ny /= $len; $nz /= $len;
                } else {
                    $nx = $ny = $nz = 0.0;
                }

                // 'g' is a little endian 32 bit float, which is what the
                // binary STL format expects regardless of host byte order.
                $out .= pack('ggg', $nx, $ny, $nz);
                $out .= pack('ggg', $a[0], $a[1], $a[2]);
                $out .= pack('ggg', $b[0], $b[1], $b[2]);
                $out .= pack('ggg', $c[0], $c[1], $c[2]);
                $out .= pack('v', 0);
            }
        }

        return $out;
    }

    /** 3MF core model XML: one <object> per box. */
    public static function modelXML(array $meshes): string
    {
        $objects = '';
        $build   = '';

        foreach ($meshes as $i => $mesh) {
            $id = $i + 1;

            $vs = '';
            foreach ($mesh['vertices'] as $v) {
                $vs .= sprintf(
                    '<vertex x="%.4F" y="%.4F" z="%.4F"/>',
                    $v[0], $v[1], $v[2]
                );
            }

            $ts = '';
            foreach ($mesh['tris'] as $t) {
                $ts .= sprintf('<triangle v1="%d" v2="%d" v3="%d"/>', $t[0], $t[1], $t[2]);
            }

            $objects .= '<object id="' . $id . '" type="model" name="Box ' . $id . '">'
                . '<mesh><vertices>' . $vs . '</vertices><triangles>' . $ts . '</triangles></mesh>'
                . '</object>';

            $build .= '<item objectid="' . $id . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<model unit="millimeter" xml:lang="en-US"'
            . ' xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02">'
            . '<resources>' . $objects . '</resources>'
            . '<build>' . $build . '</build>'
            . '</model>';
    }

    /** Complete 3MF OPC package as a binary string. */
    public static function package3MF(array $meshes): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'h3d');
        if ($tmp === false) {
            throw new RuntimeException('Cannot create a temporary file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new RuntimeException('Cannot create the 3MF archive.');
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/>'
            . '</Types>'
        );

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Target="/3D/3dmodel.model" Id="rel0"'
            . ' Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel"/>'
            . '</Relationships>'
        );

        $zip->addFromString('3D/3dmodel.model', self::modelXML($meshes));
        $zip->close();

        $data = file_get_contents($tmp);
        @unlink($tmp);

        if ($data === false) {
            throw new RuntimeException('Cannot read the generated 3MF archive.');
        }

        return $data;
    }
}
