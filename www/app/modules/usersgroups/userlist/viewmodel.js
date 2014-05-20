/*global define: true, ko: true */
define(['Boiler'], function(Boiler) {

    "use strict";

	var ViewModel = function(moduleContext) {

        var self = this;
		self.data = ko.observableArray();

        self.load = function(model) {
            Boiler.UrlController.goTo("user/" + ko.utils.unwrapObservable(model.uidnumber));
        };
        moduleContext.ds.get('/', self.data);
	};

	return ViewModel;
});
