$(function() {
    $("#city").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/suggest",
                dataType: "jsonp",
                data: {
                    f: "pjson",
                    text: request.term,
                    maxSuggestions: 5,
                    category: "City"
                },
                success: function(data) {
                    response($.map(data.suggestions, function(item) {
                        return {
                            label: item.text,
                            value: item.text,
                            magicKey: item.magicKey
                        };
                    }));
                }
            });
        },
        minLength: 3,
        select: function(event, ui) {
            if (ui.item) {
                fetchLatLng(ui.item.magicKey);
            }
        }
    });

    function fetchLatLng(magicKey) {
        $.ajax({
            url: "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/find",
            dataType: "jsonp",
            data: {
                f: "pjson",
                magicKey: magicKey,
                text: $("#city").val()
            },
            success: function(data) {
                if (data.locations.length > 0) {
                    var location = data.locations[0];
                    $("#latitude").val(location.feature.geometry.y.toFixed(2));
                    $("#longitude").val(location.feature.geometry.x.toFixed(2));
                }
            }
        });
    }
});
