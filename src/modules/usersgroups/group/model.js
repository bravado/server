/*global define: true, metaproject: true, ko: true */
define(function() {
    "use strict";

    var Model = {};

    Model.Group = metaproject.Model({
        cn: null,
        gidnumber: null,
        sambasid: null,
        sambagrouptype: null,
        displayname: null
    });

    return Model;

});