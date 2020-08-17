console.log(repObj);

const anchorDiv = document.querySelector(".anchor-div");
// const testTable = document.createElement('table');
//
// const tHead = document.createElement('thead');
// const th1 = document.createElement('th');
// th1.textContent = 'testTable head';
// tHead.appendChild(th1);
// const th2 = document.createElement('th');
// th2.textContent = 'testTable head 2';
// tHead.appendChild(th2);
// testTable.appendChild(tHead);
//
// const tr = document.createElement('tr');
// const td = document.createElement('td');
// td.textContent = 'cell';
// tr.appendChild(td);
// testTable.appendChild(tr);
//
// anchorDiv.appendChild(testTable);


const sectionHeaders = repObj.sectionHeaders;
const usersObj = repObj.usersData;
const usersKeys = Object.keys(usersObj);

// create table
const table = document.createElement('table');
table.style.fontSize = "9px";

// create header container
const header = document.createElement('thead');
// add each section header to the header container
sectionHeaders.forEach((section,i) => {
	const th = document.createElement('th');
	th.textContent = section;
	header.appendChild(th);
});
// append header container to table
table.appendChild(header);

// create body container
const body = document.createElement('tbody');
// create a row for each user & fill in data
usersKeys.forEach((key) => {
	const user = usersObj[key];
	const row = document.createElement('tr');
	// generate non-quiz-related cells
	const username = document.createElement('td');
	const name = document.createElement('td');
	const lastLogin = document.createElement('td');
	const percentComplete = document.createElement('td');

	// fill in data
	username.textContent = user.username;
	name.textContent = user.name;
	lastLogin.textContent = user.lastLogin;
	percentComplete.textContent = user.percentComplete;

	// append cells to row
	row.appendChild(username);
	row.appendChild(name);
	row.appendChild(lastLogin);
	row.appendChild(percentComplete);

	// append row to body container
	body.appendChild(row);
});

// append body container to table
table.appendChild(body);
// append table to anchor div
anchorDiv.appendChild(table);

// send data via ajax
const sendData = () => {
	const dataObj = {};
	dataObj.users = repObj.usersData;
	dataObj.action = 'ta_download_report_csv';
	dataObj._ajax_nonce = repObj.nonce;

	jQuery.ajax({
		type: 'post',
		dataType: 'json',
		url: repObj.ajax_url,
		data: dataObj,
		error: function(e) {
			console.log(`something went wrong: ${e.statusText}`);
		},
		success: function(response) {
			console.log("success!");
			console.log(response);
		}
	});
}

// button for csv download
const csvButton = document.createElement('button');
csvButton.textContent = "Download as CSV";
csvButton.addEventListener("click", (e) => {
	e.preventDefault()
	sendData()
})
anchorDiv.appendChild(csvButton);
