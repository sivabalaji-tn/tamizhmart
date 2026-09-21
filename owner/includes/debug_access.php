<?php
function ownerDebugEnabled(): bool
{
    return getenv('OWNER_DEBUG_TOOLS') === '1'
        && in_array(strtolower((string)getenv('APP_ENV')), ['development', 'test'], true);
}
