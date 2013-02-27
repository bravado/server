/*global define: true, ko: true */
define(['Boiler'], function(Boiler) {

    "use strict";

	var ViewModel = function(moduleContext) {

        var self = this;
		self.data = ko.observableArray().subscribeTo('groups', true);

        self.load = function(model) {
            Boiler.UrlController.goTo("group/" + ko.utils.unwrapObservable(model.gidnumber));
        };
	};

	return ViewModel;
});
