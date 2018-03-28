<?php

class Metrou_Hash_Argon2i {

	public function hashPassword($p) {
		if (strlen($p) > 72) {
			return FALSE;
		}
		return password_hash($p, PASSWORD_ARGON2I);
	}

	public function comparePasswordHash($p, $h) {
		return password_verify($p, $h);
	}
}
