<?php

declare(strict_types=1);

use Phai\Install\InstallInput;
use Phai\Install\Installer;

/** An install.defaults.php like the one Phai Server writes, in a temporary file. */
function presetFile(array $values): string
{
    $file = tempnam(sys_get_temp_dir(), 'phai-defaults-');
    file_put_contents($file, '<?php return ' . var_export($values, true) . ';');
    return $file;
}

test('preset password fills an empty password for the prepared database', function () {
    $file = presetFile(['db_host' => 'localhost', 'db_name' => 'phaierp', 'db_user' => 'phai_erp', 'db_pass' => 's3cret']);
    $in = new InstallInput(db_host: 'localhost', db_name: 'phaierp', db_user: 'phai_erp');
    Installer::usePresetPassword($in, $file);
    unlink($file);
    check($in->db_pass === 's3cret');
});

test('preset password is never used for another server, database or user', function () {
    $file = presetFile(['db_host' => 'localhost', 'db_name' => 'phaierp', 'db_user' => 'phai_erp', 'db_pass' => 's3cret']);
    foreach ([
        new InstallInput(db_host: '10.0.0.9', db_name: 'phaierp', db_user: 'phai_erp'),
        new InstallInput(db_host: 'localhost', db_name: 'other', db_user: 'phai_erp'),
        new InstallInput(db_host: 'localhost', db_name: 'phaierp', db_user: 'root'),
    ] as $in) {
        Installer::usePresetPassword($in, $file);
        check($in->db_pass === '', "$in->db_host/$in->db_name/$in->db_user got the preset");
    }
    unlink($file);
});

test('a typed password wins over the preset, and no file means no preset', function () {
    $file = presetFile(['db_host' => 'localhost', 'db_name' => 'phaierp', 'db_user' => 'phai_erp', 'db_pass' => 's3cret']);
    $in = new InstallInput(db_host: 'localhost', db_name: 'phaierp', db_user: 'phai_erp', db_pass: 'typed');
    Installer::usePresetPassword($in, $file);
    unlink($file);
    check($in->db_pass === 'typed');
    check(Installer::presetPassword(sys_get_temp_dir() . '/no-such-defaults.php') === '');
});
