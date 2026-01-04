//[Data Table Javascript]

//Project:      Doclinic - Responsive Admin Template
//Primary use:   Used only for the Data Table

$(function () {
  "use strict";

  var dataTablesLoadPromise = null;

  var ensureDataTablesCss = function () {
    var hasCss = $("link[rel='stylesheet']").filter(function () {
      var href = $(this).attr("href") || "";
      return href.indexOf("datatables") !== -1;
    }).length;

    if (!hasCss) {
      $("<link>", {
        rel: "stylesheet",
        href: "/public/assets/vendor_components/datatable/datatables.min.css",
      }).appendTo("head");
    }
  };

  var ensureDataTables = function () {
    if (dataTablesLoadPromise) {
      return dataTablesLoadPromise;
    }

    var deferred = $.Deferred();

    if ($.fn && typeof $.fn.DataTable === "function") {
      ensureDataTablesCss();
      dataTablesLoadPromise = deferred.resolve().promise();
      return dataTablesLoadPromise;
    }

    var sources = [
      "/public/assets/vendor_components/datatable/datatables.min.js",
      "https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js",
    ];

    var tryLoad = function (index) {
      if (index >= sources.length) {
        deferred.reject(
          new Error("Failed to load DataTables plugin from all sources.")
        );
        return;
      }

      var source = sources[index];
      var isCdn = source.indexOf("http") === 0;
      if (isCdn) {
        console.warn(
          "DataTables plugin was not found. Attempting to load fallback from",
          source
        );
      }

      $.getScript(source)
        .done(function () {
          if ($.fn && typeof $.fn.DataTable === "function") {
            if (isCdn) {
              console.info(
                "DataTables fallback from CDN loaded successfully."
              );
            }
            ensureDataTablesCss();
            deferred.resolve();
          } else {
            tryLoad(index + 1);
          }
        })
        .fail(function () {
          tryLoad(index + 1);
        });
    };

    tryLoad(0);
    dataTablesLoadPromise = deferred.promise();
    // Expose a shared promise so inline scripts can reliably wait for DataTables
    // before attempting to initialize their tables.
    if (typeof window !== "undefined") {
      window.dataTablesReadyPromise = dataTablesLoadPromise;
    }
    return dataTablesLoadPromise;
  };

  ensureDataTables().fail(function (error) {
    console.error(error.message);
  });

  var initDataTable = function (selector, options) {
    var $tables = $(selector);
    if (!$tables.length) {
      return null;
    }

    var instance = null;
    $tables.each(function () {
      var $table = $(this);

      if ($.fn.dataTable && $.fn.dataTable.isDataTable(this)) {
        instance = $table.DataTable();
      } else {
        instance = $table.DataTable(options || {});
      }
    });

    return instance;
  };

  ensureDataTables().done(function () {
    initDataTable("#example1");
    initDataTable("#example2", {
      paging: true,
      lengthChange: false,
      searching: false,
      ordering: true,
      info: true,
      autoWidth: false,
    });

    initDataTable("#example", {
      dom: "Bfrtip",
      buttons: ["copy", "csv", "excel", "pdf", "print"],
    });

    initDataTable("#insumosEditable", {
      paging: true,
      lengthChange: true,
      searching: true,
      ordering: true,
      info: true,
      autoWidth: true,
    });

    initDataTable("#productorder", {
      paging: true,
      lengthChange: true,
      searching: true,
      ordering: true,
      info: true,
      autoWidth: false,
    });

    initDataTable("#complex_header");

    var $example5Footers = $("#example5 tfoot th");
    if ($example5Footers.length) {
      $example5Footers.each(function () {
        var title = $(this).text();
        $(this).html(
          '<input type="text" placeholder="Search ' + title + '" />'
        );
      });

      var example5Table = initDataTable("#example5");
      if (example5Table) {
        example5Table.columns().every(function () {
          var column = this;

          $("input", this.footer()).on("keyup change", function () {
            if (column.search() !== this.value) {
              column.search(this.value).draw();
            }
          });
        });
      }
    }

    var example6Table = initDataTable("#example6");
    if (example6Table) {
      $("#data-update").on("click", function () {
        var data = example6Table.$("input, select").serialize();
        alert(
          "The following data would have been submitted to the server: \n\n" +
            data.substr(0, 120) +
            "..."
        );
        return false;
      });
    }
  });
}); // End of use strict
