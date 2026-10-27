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

test('a ready-made password hash stands in for the password, and the body cannot set it', function () {
    $in = new InstallInput(co_name: 'ร้านไผ่', us_userid: 'admin', us_name: 'A', us_email: 'a@example.com');
    check(isset($in->missingForInstall()['password']), 'password should be missing');
    $in->password_hash = password_hash('s3cret-pass', PASSWORD_DEFAULT);
    check($in->missingForInstall() === [], 'a hash should count as the password');
    $ctor = (new ReflectionClass(InstallInput::class))->getConstructor();
    check(!in_array('password_hash', array_map(fn ($p) => $p->getName(), $ctor->getParameters()), true), 'password_hash must not be a constructor argument');
});
