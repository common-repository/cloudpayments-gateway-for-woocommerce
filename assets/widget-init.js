const cp_pay = function () {
  var widget = new cp.CloudPayments({language: widget_data.language});
  if (widget_data.widget_f == 'auth') {
    widget.auth(
        widget_data.data,
        function (options) {
            window.location.replace(widget_data.return_url);
        },
        function (reason, options) {
            window.location.replace(widget_data.cancel_return_url);
        }
    );
  } else {
    widget.charge(
        widget_data.data,
        function (options) {
            window.location.replace(widget_data.return_url);
        },
        function (reason, options) {
            window.location.replace(widget_data.cancel_return_url);
        }
    );
  }
}

window.onload = cp_pay