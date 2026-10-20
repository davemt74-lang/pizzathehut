<?php
// Super Admin-only team mutations; never accept a role supplied by the browser.
declare(strict_types=1);
function lab_team_audit(int $target,string $action): void {
    query('INSERT INTO team_audit_log (actor_user_id,target_user_id,action) VALUES (?,?,?)',[auth(),$target,$action]);
}
function lab_team_action(string $action,array $d): never {
    require_super_admin();
    if($action==='createTeamMember') {
        $name=plain($d['display_name']??'',120);
        $email=strtolower(trim((string)($d['email']??'')));
        $pwd=(string)($d['password']??'');
        if($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) fail('Enter a name and valid email.');
        if(strlen($pwd)<12 || strlen($pwd)>1024) fail('Password must contain 12–1024 characters.');
        if(query('SELECT COUNT(*) FROM users WHERE email=?',[$email])->fetchColumn()) fail('Email already belongs to an account.',409);
        try {
            query("INSERT INTO users (display_name,email,password_hash,role,is_active) VALUES (?,?,?,'team_member',1)",[$name,$email,password_hash($pwd,PASSWORD_DEFAULT)]);
        } catch(PDOException $e) {if(($e->errorInfo[1]??0)==1062)fail('Email already belongs to an account.',409);throw $e;}
        $id=(int)db()->lastInsertId();lab_team_audit($id,'created');
        json_out(['ok'=>true,'id'=>$id]);
    }
    $id=(int)number($d['id']??0,1,2147483647,'team member ID');
    $u=query('SELECT id,role,is_active FROM users WHERE id=?',[$id])->fetch();
    if(!$u) fail('Account not found.',404);
    if($u['role']!=='team_member')fail('Super Admin accounts cannot be modified here.',403);
    if($action==='updateTeamMember') {
        $name=plain($d['display_name']??'',120);
        $email=strtolower(trim((string)($d['email']??'')));
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)fail('Enter a name and valid email.');
        $active=($d['is_active']??null);
        if(!in_array((string)$active,['0','1'],true))fail('Choose active or disabled.');
        try{query('UPDATE users SET display_name=?,email=?,is_active=? WHERE id=?',[$name,$email,(int)$active,$id]);}
        catch(PDOException $e){if(($e->errorInfo[1]??0)==1062)fail('Email already belongs to an account.',409);throw $e;}
        lab_team_audit($id,((int)$active===1)?'updated_enabled':'updated_disabled');
        json_out(['ok'=>true]);
    }
    if($action==='resetTeamPassword') {
        $password=(string)($d['password']??'');
        if(strlen($password)<12||strlen($password)>1024)fail('New password must contain 12–1024 characters.');
        query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);
        lab_team_audit($id,'password_reset');
        json_out(['ok'=>true]);
    }
    fail('Unknown team management action',404);
}
