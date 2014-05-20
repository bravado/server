<?php

include "../_init.php";


route("GET /?", "group_list");
route("GET /([a-z_.0-9]+)/?", "group_get");
route("PUT /([a-z_.0-9]+)/?", "group_update");
route("POST /?", 'group_create');


