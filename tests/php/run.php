<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Camagru\Security\Auth;
use Camagru\Database\Database;
use Camagru\Media\Gif;
use Camagru\Http\Http;
use Camagru\Http\HttpError;
use Camagru\Media\Images;
use Camagru\Mail\Outbox;

$passed = 0;
$failed = 0;
function check(bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function rejects(callable $callback, int $status): void {
    try { $callback(); } catch (HttpError $error) { check($error->status === $status, 'Unexpected rejection status'); return; }
    throw new RuntimeException('Expected request to be rejected');
}
function test(string $name, callable $callback): void {
    global $passed,$failed;
    try { $callback(); $passed++; echo "PASS $name\n"; }
    catch (Throwable $error) { $failed++; echo "FAIL $name: " . $error->getMessage() . "\n"; }
}
function isolated(callable $callback): void {
    Database::connection()->beginTransaction();
    try { $callback(); } finally { Database::connection()->rollBack(); }
}
function fixture(int $red = 10, int $green = 20, int $blue = 30): GdImage {
    $image = imagecreatetruecolor(800,600);
    imagefill($image,0,0,imagecolorallocate($image,$red,$green,$blue));
    return $image;
}

test('Password policy, Argon2id hashing, and verification', static function (): void {
    $password = Auth::validatePassword('a very long password 123', 'a very long password 123');
    $hash = Auth::hash($password);
    check(str_starts_with($hash, '$argon2id$'));
    check(password_verify($password,$hash));
    check(!password_verify('wrong',$hash));
    rejects(static fn () => Auth::validatePassword('weak1','weak1'),422);
    rejects(static fn () => Auth::validatePassword(str_repeat('a',15),str_repeat('a',15)),422);
    rejects(static fn () => Auth::validatePassword($password,'different'),422);
});
test('Identity validation and HTML escaping', static function (): void {
    check(Auth::validateEmail(' Someone@Example.COM ') === 'someone@example.com');
    check(Auth::validateUsername(' Good_name ') === 'Good_name');
    rejects(static fn () => Auth::validateUsername('<script>'),422);
    rejects(static fn () => Auth::validateEmail("bad\n@example.com"),422);
    check(Http::escape('<script>"&') === '&lt;script&gt;&quot;&amp;');
});
test('Outbox payload authentication and confidentiality', static function (): void {
    $data = ['token' => 'a secret bearer link', 'unicode' => 'a little café'];
    $encrypted = Outbox::encrypt($data);
    check(!str_contains($encrypted,'a secret'));
    check(Outbox::decrypt($encrypted) === $data);
    $tampered = base64_decode($encrypted);
    $tampered[30] = chr(ord($tampered[30]) ^ 1);
    try { Outbox::decrypt(base64_encode($tampered)); } catch (RuntimeException) { return; }
    throw new RuntimeException('Tampered payload was accepted');
});
test('CSRF rejects missing tokens and foreign origins', static function (): void {
    $_SESSION = ['csrf' => str_repeat('a',64)]; $_POST = [];
    rejects(static fn () => Auth::csrf(),403);
    $_POST['csrf'] = str_repeat('a',64); $_SERVER['HTTP_ORIGIN'] = 'https://attacker.example';
    rejects(static fn () => Auth::csrf(),403);
    unset($_SERVER['HTTP_ORIGIN']); Auth::csrf();
    $_POST = [];
});
test('Transparent compositing preserves center and applies frame', static function (): void {
    $source = fixture(); $output = Images::composite($source,'postcard');
    check(imagesx($output) === 1280 && imagesy($output) === 960);
    check((imagecolorat($output,640,480) & 0xffffff) === 0x0a141e, 'Transparent center changed');
    check((imagecolorat($output,25,25) & 0xffffff) !== 0x0a141e, 'Frame did not composite');
    rejects(static fn () => Images::composite($source,'../../etc/passwd'),422);
});
test('Upload decoder rejects unwanted and damaged input', static function (): void {
    $path = tempnam(sys_get_temp_dir(),'camagru-test-');
    try {
        file_put_contents($path,'<svg xmlns="http://www.w3.org/2000/svg"/>');
        rejects(static fn () => Images::decode($path,filesize($path)),422);
        rejects(static fn () => Images::decode($path,6*1024*1024),413);
        imagepng(fixture(),$path); clearstatcache(true,$path);
        $decoded = Images::decode($path,filesize($path));
        check(imagesx($decoded) === 800);
        file_put_contents($path,"\x89PNG\r\n\x1a\ninvalid"); clearstatcache(true,$path);
        rejects(static fn () => Images::decode($path,filesize($path)),422);
    } finally { unlink($path); }
});
test('All six overlays are RGBA and have a transparent center', static function (): void {
    foreach (Images::OVERLAYS as $key => $label) {
        $layer = imagecreatefrompng(dirname(__DIR__, 2) . '/public/assets/images/overlays/' . $key . '.png');
        check(imagesx($layer) === 1280 && imagesy($layer) === 960);
        check((imagecolorat($layer,640,480) >> 24) === 127, $label . ' center is not transparent');
    }
});
test('GIF assembler produces a multi-frame artifact for independent decoding', static function (): void {
    $frames = [];
    foreach ([[220,20,20],[20,180,30],[30,50,220]] as [$r,$g,$b]) {
        $image = Images::composite(fixture($r,$g,$b),'postcard',480,360);
        imagetruecolortopalette($image,true,256);
        ob_start(); imagegif($image); $frames[] = ob_get_clean();
    }
    $gif = Gif::assemble($frames);
    check(str_starts_with($gif,'GIF89a'));
    check(str_ends_with($gif,';'));
    check(substr_count($gif,"\x21\xf9\x04") === 3);
    file_put_contents('/tmp/camagru-test.gif',$gif);
    rejects(static fn () => Gif::assemble([$frames[0]]),422);
});
test('Database identities, defaults, SQL parameters, and like uniqueness', static function (): void {
    isolated(static function (): void {
        $id = Database::query('INSERT INTO users(username,email,password_hash) VALUES (?,?,?) RETURNING id', ['unit_owner','unit_owner@example.com',Auth::hash('unit password with 123')])->fetchColumn();
        check(Database::query('SELECT notify_comments FROM users WHERE id=?',[$id])->fetchColumn() === true);
        check(!Database::query('SELECT id FROM users WHERE username=?',["' OR 1=1 --"])->fetchColumn());
        $photo = Database::query("INSERT INTO photos(user_id,kind,state,media_key,width,height,submission_key) VALUES (?,'photo','ready',?,1280,960,?) RETURNING id",[$id,bin2hex(random_bytes(16)),bin2hex(random_bytes(16))])->fetchColumn();
        Database::query('INSERT INTO likes(photo_id,user_id) VALUES (?,?) ON CONFLICT DO NOTHING',[$photo,$id]);
        Database::query('INSERT INTO likes(photo_id,user_id) VALUES (?,?) ON CONFLICT DO NOTHING',[$photo,$id]);
        check((int) Database::query('SELECT COUNT(*) FROM likes WHERE photo_id=?',[$photo])->fetchColumn() === 1);
    });
});
test('Rate limits persist and expire correctly', static function (): void {
    isolated(static function (): void {
        Auth::rate('unit',2,60,'unit'); Auth::rate('unit',2,60,'unit');
        rejects(static fn () => Auth::rate('unit',2,60,'unit'),429);
    });
});
test('Account token storage hashes secrets and invalidates previous tokens', static function (): void {
    isolated(static function (): void {
        $id = (int) Database::query('INSERT INTO users(username,email,password_hash) VALUES (?,?,?) RETURNING id',['unit_token','unit_token@example.com','test'])->fetchColumn();
        $first = Auth::token($id,'reset'); $second = Auth::token($id,'reset');
        check(strlen($second) === 64 && $first !== $second);
        check((int) Database::query('SELECT COUNT(*) FROM account_tokens WHERE user_id=? AND consumed_at IS NULL',[$id])->fetchColumn() === 1);
        check(Database::query('SELECT token_hash FROM account_tokens WHERE user_id=? AND consumed_at IS NULL',[$id])->fetchColumn() === hash('sha256',$second));
        $payload = Database::query('SELECT payload FROM email_outbox WHERE user_id=? ORDER BY id DESC LIMIT 1',[$id])->fetchColumn();
        check(!str_contains($payload,$second));
        check(str_contains(Outbox::decrypt($payload)['body'],$second));
    });
});
test('Account links are purpose-bound, expiring, and single-use', static function (): void {
    $suffix = bin2hex(random_bytes(5));
    $id = (int) Database::query('INSERT INTO users(username,email,password_hash) VALUES (?,?,?) RETURNING id',['unit_' . $suffix,'unit_' . $suffix . '@example.test','test'])->fetchColumn();
    try {
        $token = Database::transaction(static function () use ($id): string {
            $raw = Auth::token($id,'verify');
            Database::query("UPDATE email_outbox SET next_attempt_at=NOW()+INTERVAL '1 day' WHERE user_id=?",[$id]);
            return $raw;
        });
        rejects(static fn () => Auth::consume($token,'reset',static fn () => null),422);
        Auth::consume($token,'verify',static function (array $row): void { Database::query('UPDATE users SET verified_at=NOW() WHERE id=?',[$row['user_id']]); });
        check((bool) Database::query('SELECT verified_at FROM users WHERE id=?',[$id])->fetchColumn());
        rejects(static fn () => Auth::consume($token,'verify',static fn () => null),422);
        Database::query("UPDATE account_tokens SET consumed_at=NULL,expires_at=NOW()-INTERVAL '1 second' WHERE user_id=?",[$id]);
        rejects(static fn () => Auth::consume($token,'verify',static fn () => null),422);
    } finally { Database::query('DELETE FROM users WHERE id=?',[$id]); }
});
test('Case-insensitive account uniqueness is enforced in the database', static function (): void {
    isolated(static function (): void {
        Database::query('INSERT INTO users(username,email,password_hash) VALUES (?,?,?)',['unit_unique','unit_unique@example.test','test']);
        try {
            Database::query('INSERT INTO users(username,email,password_hash) VALUES (?,?,?)',['UNIT_UNIQUE','other_unique@example.test','test']);
        } catch (PDOException $error) { check($error->getCode() === '23505'); return; }
        throw new RuntimeException('Duplicate username was accepted');
    });
});
test('Malformed multipart shapes are rejected before decoding', static function (): void {
    rejects(static fn () => Images::upload(['error' => [0], 'tmp_name' => ['bad'], 'size' => [10]]),422);
    rejects(static fn () => Images::upload([]),422);
});
echo "$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
