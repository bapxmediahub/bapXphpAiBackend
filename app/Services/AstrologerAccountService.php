<?php
namespace App\Services;

final class AstrologerAccountService {
    public const INITIAL_PASSWORD = 'sripanjamiconsult';
    public function __construct(private JsonStoreService $store = new JsonStoreService()) {}

    public function sync(array $profile): array {
        $slug=(string)($profile['slug']??'');
        if($slug==='') throw new \InvalidArgumentException('Astrologer slug is required.');
        $username=trim((string)($profile['username']??''));
        if($username==='') $username=str_replace('-','.',$slug);
        $users=$this->store->read('users');
        foreach($users as $index=>$user){
            if(($user['role']??'')!=='astrologer'||($user['astrologer_slug']??'')!==$slug) continue;
            $users[$index]['username']=$username; $users[$index]['name']=$profile['name']??$user['name']??'';
            $this->store->write('users',$users); return $users[$index];
        }
        $account=['id'=>bin2hex(random_bytes(8)),'email'=>'','username'=>$username,'name'=>$profile['name']??'','role'=>'astrologer','astrologer_slug'=>$slug,'must_change_password'=>true,'password_hash'=>password_hash(self::INITIAL_PASSWORD,PASSWORD_DEFAULT)];
        $this->store->upsert('users',$account); return $account;
    }

    public function deleteForSlug(string $slug): void {
        $users=array_values(array_filter($this->store->read('users'),fn($user)=>(($user['role']??'')!=='astrologer'||($user['astrologer_slug']??'')!==$slug)));
        $this->store->write('users',$users);
    }
}
