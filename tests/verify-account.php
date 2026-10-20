<?php
declare(strict_types=1);
require __DIR__.'/../account_lib.php';
function test_account(bool $ok,string $message): void {if(!$ok)throw new RuntimeException('Account test failed: '.$message);}
$profile=lab_my_profile_input(['display_name'=>'  Test Chef  ','email'=>' CHEF@Example.COM ']);
test_account($profile['display_name']==='Test Chef' && $profile['email']==='chef@example.com','normalized profile');
foreach ([['display_name'=>'','email'=>'chef@example.com'],['display_name'=>str_repeat('a',121),'email'=>'chef@example.com'],['display_name'=>'Chef','email'=>'invalid'],['display_name'=>'Chef','email'=>str_repeat('a',190).'@example.com']] as $input){
    try{lab_my_profile_input($input);throw new RuntimeException('Invalid profile accepted');}catch(InvalidArgumentException $expected){}
}
$hash=password_hash('strong-private-password',PASSWORD_DEFAULT);
test_account(lab_profile_email_change_authorized('a@example.com','A@EXAMPLE.COM','',$hash),'name only avoids password challenge');
test_account(!lab_profile_email_change_authorized('a@example.com','b@example.com','wrong',$hash),'email change denied without current password');
test_account(lab_profile_email_change_authorized('a@example.com','b@example.com','strong-private-password',$hash),'email change requires password');
$api=file_get_contents(__DIR__.'/../api.php');$chat=file_get_contents(__DIR__.'/../agent_chat.php');$client=file_get_contents(__DIR__.'/../assets/agent_chat_account.js');$voice=file_get_contents(__DIR__.'/../assets/agent_chat_voice.js');
test_account(str_contains($api,"if (\$action==='updateMyProfile')")&&str_contains($api,'lab_profile_email_change_authorized'),'API enforces profile auth');
test_account(str_contains($api,"(int)\$u['id']")&&str_contains($api,"require_once __DIR__.'/account_lib.php'"),'own-user-only update');
test_account(str_contains($api,'check_csrf();'),'API CSRF gate');
foreach (['id="chatNavToggle"','id="chatAccountToggle"','id="chatAccountMenu"','id="chatAccountDialog"','id="chatProfileForm"','id="chatVoiceForm"','id="chatPasswordForm"','id="chatMic"','id="chatCamera"','id="question"','id="send"'] as $piece)test_account(str_contains($chat,$piece),'chat minimal structure '.$piece);
test_account(str_contains($chat,'action="auth.php?logout=1"')&&str_contains($chat,'name="csrf"'),'POST logout with CSRF');
test_account(str_contains($client,"'updateMyProfile'")&&str_contains($client,"'saveVoiceProfile'")&&str_contains($client,"'changeMyPassword'")&&str_contains($client,'confirm_password'),'on-chat account actions');
test_account(str_contains($client,'X-CSRF-Token')&&str_contains($client,'textContent')&&!str_contains($client,'innerHTML'),'account controls use CSRF and safe text');
test_account(str_contains($voice,'PIZZA_CHAT_LANGUAGE')&&str_contains($voice,'PIZZA_CHAT_VOICE'),'chat voice settings actually used');
echo "PASS: account profiles, email reauthentication, CSRF/source scoping, voice configuration and minimal chat navigation\n";
