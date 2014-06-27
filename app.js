'use strict';

angular.module('app', ['ngSanitize'])

/*******************************************************************************************
 * AppController
 *******************************************************************************************/

.controller('AppCtrl', ['$scope', '$http', '$window', function($scope, $http, $window) {

  var prevQuery = null;

  $scope.query = '';
  $scope.idx = 'villvest';
  $scope.resultsCount = 0;
  $scope.results = [];

  function parseQs() {

    var a = window.location.search.substr(1).split('&')
        if (a == "") return {};
        var b = {};
        for (var i = 0; i < a.length; ++i)
        {
            var p=a[i].split('=');
            if (p.length != 2) continue;
            b[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
        }
        return b;
    }



  $scope.search = function() {
    $scope.busy = true;
    console.log('Søker etter ' + $scope.query + ' i ' + $scope.idx);

    var qs = '?query=' + $scope.query + '&idx=' + $scope.idx;
    history.pushState(null, null, './' + qs);

    $http.get('backend.php' + qs)
    .success(function(response) {
      $scope.busy = false;
      $scope.resultsCount = response.hits;
      $scope.results = response.docs;
      $scope.results.forEach(function(result) {
        result.libraries.forEach(function(library) {
          var m = library.collection.match(/<span id="dokid">(.*)<\/span>(.*)$/);
          library.dokid = m[1];
          library.collection = m[2];
        });
      });
    })
    .error(function() {
      $scope.busy = false;
      alert('Søket feilet!');
    });
  };

  angular.element($window).bind('popstate', function() {
    var qs = parseQs();
    if (qs.query) {
      $scope.query = qs.query;
      if (qs.idx) {
        $scope.idx = qs.idx;
      }
      $scope.$apply();
    }
  });

  var qs = parseQs();
  if (qs.query) {
    $scope.query = qs.query;
    if (qs.idx) {
      $scope.idx = qs.idx;
    }
    $scope.search();
  }

}]);
