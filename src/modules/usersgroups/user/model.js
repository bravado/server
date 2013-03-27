/*global define: true, metaproject: true, ko: true */
define(function() {
    "use strict";

    var Model = {};

    Model.User = metaproject.Model({
        uid: null,
        uidnumber: null,
        gidnumber: null,
        gecos: null,
        passwd: null
    });

    return Model;

});