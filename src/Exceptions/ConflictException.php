<?php

namespace Osaris\Discogs\Exceptions;

/**
 * 409: for example an inventory export or upload is already in progress.
 */
class ConflictException extends DiscogsException {}
