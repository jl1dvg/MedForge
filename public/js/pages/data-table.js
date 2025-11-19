//[Data Table Javascript]

//Project:      Doclinic - Responsive Admin Template
//Primary use:   Used only for the Data Table

$(function () {
  "use strict";

  var ensureDataTables = function () {
    var deferred = $.Deferred();

    if ($.fn && typeof $.fn.DataTable === "function") {
      deferred.resolve();
      return deferred.promise();
    }

    var cdnUrl = "https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js";
    console.warn(
      "DataTables plugin was not found. Attempting to load fallback from",
      cdnUrl
    );

    $.getScript(cdnUrl)
      .done(function () {
        if ($.fn && typeof $.fn.DataTable === "function") {
          console.info("DataTables fallback from CDN loaded successfully.");
          deferred.resolve();
        } else {
          deferred.reject(
            new Error(
              "DataTables fallback loaded but plugin is still unavailable."
            )
          );
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        deferred.reject(
          new Error(
            "Failed to load DataTables fallback (" +
              textStatus +
              ") " +
              errorThrown
          )
        );
      });

    return deferred.promise();
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
