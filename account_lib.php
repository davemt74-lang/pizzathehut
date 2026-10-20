<?php
declare(strict_types=1);
/** Account profile validation with no role or privileged setting changes. */
function lab_my_profile_input(array $input): array {
    $name=trim((string)($input['display_name']??''));
    $email=strtolower(trim((string)($input['email']??'')));
    if($name==='' || (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name))>120)throw new InvalidArgumentException('Display name must be 1–120 characters.');
    if(strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email address.');
    return ['display_name'=>$name,'email'=>$email];
}
/** Require existing password when modifying the sign-in address, but not the display name. */
function lab_profile_email_change_authorized(string $oldEmail,string $newEmail,string $currentPassword,string $passwordHash): bool {
    return strtolower($oldEmail)===strtolower($newEmail) || password_verify($currentPassword,$passwordHash);
}
