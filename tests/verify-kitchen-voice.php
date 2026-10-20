<?php
$voice=file_get_contents(__DIR__.'/../assets/agent_chat_voice.js');
$chat=file_get_contents(__DIR__.'/../agent_chat.php');
foreach(['isSecureContext','SpeechRecognition','pizza-chat-mic-start','pizza-chat-reply-error','speechSynthesis.cancel','pizza-chat-photo-command','onend','onerror','voiceQuestion=false','aria-pressed','pagehide'] as $token){
 if(!str_contains($voice,$token))throw new RuntimeException('Missing voice contract: '.$token);
}
if(!str_contains($chat,"new Event('pizza-chat-reply-error')"))throw new RuntimeException('Failed request must clear voice reply flag');
echo "PASS: voice interruption, permissions and error recovery\n";
