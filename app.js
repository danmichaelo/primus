'use strict';

angular.module('app', ['ngSanitize', 'ngTouch', 'ui.bootstrap'])

/*******************************************************************************************
 * AppController
 *******************************************************************************************/

.controller('AppCtrl', ['$scope', '$http', '$window', '$timeout', '$q', 'PrimoApi', function($scope, $http, $window, $timeout, $q, PrimoApi) {

  var prevQuery = null;

  $scope.resultsCount = 0;
  $scope.results = [];
  $scope.error = '';
  $scope.query = {};
  var resultSets = {};
  var canceler;

  var smalldelay;
  $scope.$watch('query', function(newVal, oldVal) {

    //console.log(oldVal);
    //console.log(newVal);
    if (oldVal == newVal) {
      return;
    }

    $scope.error = '';

    if (smalldelay) {
      $timeout.cancel(smalldelay);  // Aborts waiting, if active
    }

    // injectResults();  // Remove the results to give some visual indication that something has changed
    if (canceler) {
      log('Avbryt');
      canceler.resolve('derfor');  // Aborts the $http request if it isn't finished.
    }

    smalldelay = $timeout(function() {

      $window.document.title = 'Primus : ' + newVal.keyword;
      var qs = serializeQuery(newVal);
      if (!resultSets[qs]) {
        // injectResults();
        search();
      } else {
        injectResults(qs);
      }

    }, 400);

  }, true);

  $scope.wait_time = 0.0;

  function log(s) {
    console.log((new Date()).getTime() + ' - ' + s);
  }

  function timeit() {
    var startTime = (new Date).getTime()/1000.;
    $timeout(function inctime() {
      $scope.wait_time = (new Date).getTime()/1000. - startTime; 
      if ($scope.busy) $timeout(inctime, 100);
    }, 100);
  }

  $scope.selectCreator = function(c) {
    console.log('selecting creator');
    console.log(c);
  };

  function injectResults (qs) {
    if (!qs) {
      $scope.total_results = undefined;
      $scope.documents = [];
      $scope.wait_time = '';
      return;
    }
    $scope.total_results = resultSets[qs].total_results;
    $scope.documents = resultSets[qs].docs;    
    $scope.wait_time = resultSets[qs].load_time;
  }

  function search() {

    // Clear search?
    if (!$scope.query.keyword || getQueryScopes($scope.query).length == 0) {
      injectResults();
      return;
    }

    var qs = serializeQuery($scope.query);
    addToHistory(qs);

    log('Søker etter: ' + qs);
    canceler = $q.defer();
    $scope.busy = true;
    timeit();
    PrimoApi.search({
      action: 'search',
      query: $scope.query.keyword,
      idx: $scope.query.idx,
      lang: $scope.query.lang,
      scope: getQueryScopes($scope.query).join(',')
    }, {timeout: canceler.promise}).then(function(response) {

      $scope.busy = false;
      log('Søk ferdig: ' + qs);
      console.log(response);

      if (response.error) {
        console.log('ERROR: ' + response.error);
        $scope.error = response.error;
        return;
      }

      response.load_time = $scope.wait_time;
      resultSets[qs] = response;
      injectResults(qs);

    }, function() {

      $scope.busy = false;
      log('Avbryter søk: ' + qs);

    });
  }

  // -------------------------------------------------------------------
  // History
  // -------------------------------------------------------------------

  function getQueryScopes (query) {
    var scopes = [];
    scopes.push(query.scope);
    // if (query.scope == 'bibsys') scopes.push('duo');

    /*if (query.scope_ubo) scopes.push('ubo');
    if (query.scope_bibsys) scopes.push('bibsys');
    if (query.scope_primo) scopes.push('primo');
    if (query.scope_duo) scopes.push('duo');
    */
    //console.log(scopes);
    return scopes;
  }

  function serializeQuery(query) {
    var scopes = getQueryScopes(query);
    return '?query=' + query.keyword + '&idx=' + query.idx + '&lang=' + query.lang + '&scope=' + scopes.join(',');
  }

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

  function addToHistory(qs) {
    history.pushState(null, null, './' + qs);
  }

  function queryFromHistory(qs) {
    $scope.query = {};
    $scope.query.keyword = qs.query;
    $scope.query.idx = qs.idx ? qs.idx : 'villvest';
    $scope.query.lang = qs.lang ? qs.lang : 'nor';
    $scope.query.scope = qs.scope ? qs.scope : 'ubo';
    if (qs.scope) {
      var scopes = qs.scope.split(',');
      $scope.query.scope_ubo = (scopes.indexOf('ubo') !== -1);
      $scope.query.scope_bibsys = (scopes.indexOf('bibsys') !== -1);
      $scope.query.scope_primo = (scopes.indexOf('primo') !== -1);
      $scope.query.scope_duo = (scopes.indexOf('duo') !== -1);
    } else {
      $scope.query.scope_ubo = false;
      $scope.query.scope_bibsys = true;
      $scope.query.scope_primo = true;
      $scope.query.scope_duo = false;
    }
  }

  angular.element($window).bind('popstate', function() {
    var qs = parseQs();
    queryFromHistory(qs);
    $scope.$apply();
  });

  var qs = parseQs();
  queryFromHistory(qs);
  if ($scope.query.keyword) {
    search();
  }

}])


.directive('editions', ['$http', 'PrimoApi', function ($http, PrimoApi) {
  return {

    restrict : 'E', // element names only
    templateUrl: 'templates/work.html',
    scope: true,

    link: function(scope, element, attrs) {

      function fetch(id) {
        scope.bibsys_id = attrs.workId;
        console.log('Fetching work title');
        PrimoApi.search({
          workId: attrs.workId,
          scope: scope.query.scope,
          action: 'getWork'
        }).then(function(response) {
          console.log(response);
          scope.subdocs = response.docs;
        });

        // LocalApi.search('bs.objektid=' + id).then(function(docs) {
        //   console.log(docs);
        //   console.log('setting ' + scope.title);
        //   scope.title = docs[0].title;
        //   scope.part = attrs.itemPart;
        // });
      }

      if (attrs.workId && attrs.workVersions > 1) {
        fetch(attrs.workId);
      } else {
        
      }

    }
  };
}])


.directive('cover', ['$http', function ($http) {
  return {

    restrict : 'E', // element names only
    templateUrl: 'templates/cover.html',
    scope: false, // inherit

    link: function(scope, element, attrs) {

      function fetch(doc) {

        // console.log(doc.id + ' : ' + doc.cover_images.length);

        var waiting = doc.cover_images.length;

        if (!doc.cover_images.length) {
          scope.imgsrc = "blank.jpg";
        }
        doc.cover_images.forEach(function(url) {
          //console.log('Check ' + url);
          var img = new Image();
          img.src = url;

          function done() {
            waiting--;
            scope.$apply(function() {
              if (waiting <= 0 && !scope.imgsrc) {
                // console.log("USE BLANK");
                scope.imgsrc = "blank.jpg";
              }
            });
          }

          img.onerror = function() {
            done();
          };
          img.onload = function() {
            scope.$apply(function() {
              if (img.width > 1) {
                //console.log('Found a usable cover image');
                //console.log(scope);
                scope.imgsrc = img.src;
              }
            });
            done();
          };

        });


      }

     //console.log(scope);
      //console.log(scope.subdoc.covers);

      if (scope.subdoc) {
        fetch(scope.subdoc);
      } else if (scope.doc) {
        fetch(scope.doc);
      }

    }
  };
}])

.service('PrimoApi', ['$http', '$q', function($http, $q) {

  this.search = function(params, args) {

    var deferred = $q.defer();

    $http({
      url: 'backend2.php',
      method: 'GET',
      params: params
    }, args)
    .error(function(response, status, headers, config) {
      deferred.reject(status);
    })
    .success(function(data) {

      if (data.docs) {

        data.docs.forEach(function(doc) {
          if (Array.isArray(doc.title)) {
            doc.title = doc.title[0];
          }
          doc.creator_facet = doc.creator_facet.filter(function(el) {
            if (el.match(/Online service/)) return false;
            return true;
          });
        });

        for (var i = data.docs.length - 1; i >= 0; i--) {
          var libs = [];
          for (var j = data.docs[i].libraries.length - 1; j >= 0; j--) {
            var lib = data.docs[i].libraries[j];
            if (lib.institution == 'UBO') {
              libs.push(lib);
            }
          }
          console.log(libs);
          data.docs[i].librariesUreal = libs;
        }
      }

      deferred.resolve(data);
    });
  
    return deferred.promise;
  };

}])

;
