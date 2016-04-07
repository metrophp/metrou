<?php

class Metrou_Hash_Legacy {

	public function hashPassword($p) {
		return md5(sha1($p));
	}

	public function comparePasswordHash($p, $h) {
		return $this->hashPassword($p) === $h;
	}
}
