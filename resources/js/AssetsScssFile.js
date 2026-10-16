import { promises as fs } from "fs";

export class AssetsScssFile {
    /**
     * @param {string} [destination="./resources/scss/_assets.scss"] - Where the
     * generated variables file is written. Keep it one directory level above
     * the published SCSS mixins (`@use "../assets"`).
     */
    constructor(destination = "./resources/scss/_assets.scss") {
        this.destination = destination;
    }

    async createSCSSFile(variables) {
        this.mappedVariables = variables;
        try {
            await fs.writeFile(this.destination, this.getScssContent());
            console.log(this.destination + " file successfully created!");
        } catch (err) {
            console.error("An error occurred:", err);
        }
    }

    getScssContent() {
        let content = "/**** Auto-generated file, DO NOT EDIT ****/";

        this.mappedVariables.forEach((value, variable) => {
            const parsedValue = Boolean(value) ? `'${value}'` : value;
            content += ` ${variable}:${parsedValue};`;
        });

        return content;
    }
}
