<?php

class Metrou_Hash_Bcrypt {

	public function hashPassword($p) {
		if (strlen($p) > 72) {
			return FALSE;
		}
		return password_hash($p, PASSWORD_BCRYPT);
	}

	public function comparePasswordHash($p, $h) {
		return password_verify($p, $h);
	}
}
