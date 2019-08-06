# Cacti Flow Service

The two files in this directory are for init.d and systemd service control files for the flowview plugin services

# Features

There are two types of collectors for flowview, one requires only Cacti and the second requires flow-tools binary package.

# Installation

## Init.d Based Systems

* First, copy the flow-capture file into /etc/init.d/

* Then, run 'chkconfig --add flow-capture

* Then, edit /etc/init.d/flow-capture and ensure the cacti_base variable is set properly

* Lastly, run: 

	/etc/init.d/flow-capture start 

This will start the service.

## SystemD Based Systems

* First, copy the file flow-capture.service into /lib/systemd/system

* Then, edit the file and ensure that the path to the flow-capture script is accurate

* Then, run 'systemtl enable flow-capture'

* Lastly, run:

	systemctl start flow-capture

followed by:

	systemctl status flow-capture

To verify that the service is actually running as expected.

